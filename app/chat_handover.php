<?php
declare(strict_types=1);

/**
 * Durable website-chat handover storage. A handover is a CRM lead, not a
 * duplicate paid booking. The existing booking flow remains responsible for
 * confirmed recovery requests and deposits.
 */

function ensure_chat_handover_schema(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS chat_leads (
            id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_key           CHAR(64) NOT NULL,
            reference             VARCHAR(12) NOT NULL,
            name                  VARCHAR(120) NOT NULL DEFAULT '',
            email                 VARCHAR(190) NOT NULL DEFAULT '',
            phone                 VARCHAR(30) NOT NULL DEFAULT '',
            vehicle_make          VARCHAR(80) NOT NULL DEFAULT '',
            vehicle_model         VARCHAR(120) NOT NULL DEFAULT '',
            vehicle_reg           VARCHAR(20) NOT NULL DEFAULT '',
            address               VARCHAR(255) NOT NULL DEFAULT '',
            postcode              VARCHAR(12) NOT NULL DEFAULT '',
            current_location      VARCHAR(255) NOT NULL DEFAULT '',
            destination           VARCHAR(255) NOT NULL DEFAULT '',
            problem               TEXT,
            required_time         VARCHAR(120) NOT NULL DEFAULT '',
            service               VARCHAR(120) NOT NULL DEFAULT '',
            distance_miles        VARCHAR(30) NOT NULL DEFAULT '',
            conversation_json     LONGTEXT NOT NULL,
            handover_message      TEXT NOT NULL,
            status                ENUM('open','handover_requested','callback_requested','closed') NOT NULL DEFAULT 'open',
            handover_channel      VARCHAR(30) NOT NULL DEFAULT '',
            handover_requested_at DATETIME NULL,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_key (session_key),
            UNIQUE KEY reference (reference),
            KEY status (status),
            KEY updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    db()->exec(
        "CREATE TABLE IF NOT EXISTS chat_lead_events (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id    INT UNSIGNED NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            message    VARCHAR(255) NOT NULL,
            channel    VARCHAR(30) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function chat_handover_clean_value($value, int $maxLength): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }
    $value = trim((string) $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

/** @return array<string,string> */
function chat_handover_clean_details($details): array
{
    if (!is_array($details)) {
        return [];
    }
    $limits = [
        'name' => 120, 'email' => 190, 'phone' => 30,
        'vehicle_make' => 80, 'vehicle_model' => 120, 'vehicle_reg' => 20,
        'address' => 255, 'postcode' => 12, 'current_location' => 255,
        'destination' => 255, 'problem' => 2000, 'required_time' => 120,
        'service' => 120, 'distance_miles' => 30, 'notes' => 2000,
    ];
    $clean = [];
    foreach ($limits as $key => $limit) {
        $value = chat_handover_clean_value($details[$key] ?? '', $limit);
        if ($value !== '') {
            $clean[$key] = $value;
        }
    }
    if (!isset($clean['problem']) && isset($clean['notes'])) {
        $clean['problem'] = $clean['notes'];
    }
    if (!isset($clean['current_location']) && isset($clean['address'])) {
        $clean['current_location'] = trim($clean['address'] . (isset($clean['postcode']) ? ', ' . $clean['postcode'] : ''));
    }
    return $clean;
}

/** @return array<int,array{role:string,content:string}> */
function chat_handover_clean_history($history): array
{
    if (!is_array($history)) {
        return [];
    }
    $clean = [];
    foreach (array_slice($history, -30) as $item) {
        if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
            continue;
        }
        $content = chat_handover_clean_value($item['content'] ?? '', 3000);
        if ($content !== '') {
            $clean[] = ['role' => (string) $item['role'], 'content' => $content];
        }
    }
    return $clean;
}

function chat_handover_requested(string $message): bool
{
    return preg_match('/\b(human|agent|whatsapp|real person|speak to (a )?(human|someone|somebody|person)|talk to (the )?team|need help)\b/i', $message) === 1;
}

/** @param array<string,string> $details */
function chat_handover_message(string $reference, array $details): string
{
    $lines = [
        'Hi MancWay Recovery 👋',
        '',
        'I was using your website chatbot and asked to speak to someone.',
        '',
        'Reference: ' . $reference,
    ];
    $fields = [
        'Name' => 'name',
        'Vehicle' => 'vehicle',
        'Registration' => 'vehicle_reg',
        'Current location' => 'current_location',
        'Destination' => 'destination',
        'Problem' => 'problem',
        'Required' => 'required_time',
    ];
    $vehicle = trim(($details['vehicle_make'] ?? '') . ' ' . ($details['vehicle_model'] ?? ''));
    if ($vehicle !== '') {
        $details['vehicle'] = $vehicle;
    }
    foreach ($fields as $label => $key) {
        if (!empty($details[$key])) {
            $lines[] = $label . ': ' . $details[$key];
        }
    }
    $lines[] = '';
    $lines[] = 'I would like to continue with a member of the team.';
    return implode("\n", $lines);
}

/** @param array<string,string> $details */
function chat_handover_detail_lines(array $details): array
{
    $lines = [];
    $labels = [
        'name' => 'Customer', 'email' => 'Email', 'phone' => 'Phone',
        'vehicle_make' => 'Vehicle make', 'vehicle_model' => 'Vehicle model',
        'vehicle_reg' => 'Registration', 'current_location' => 'Location',
        'destination' => 'Destination', 'problem' => 'Problem',
        'required_time' => 'Required', 'service' => 'Service',
    ];
    foreach ($labels as $key => $label) {
        if (!empty($details[$key])) {
            $lines[] = $label . ': ' . $details[$key];
        }
    }
    return $lines;
}

/** @param array<int,array{role:string,content:string}> $history */
function chat_handover_save(array $details, array $history, string $sessionKey, string $mode = 'whatsapp'): array
{
    ensure_chat_handover_schema();
    $sessionKey = preg_replace('/[^A-Za-z0-9_-]/', '', $sessionKey) ?? '';
    if ($sessionKey === '') {
        $sessionKey = hash('sha256', session_id() . '|' . client_ip());
    }
    $sessionKey = substr($sessionKey, 0, 64);
    $details = chat_handover_clean_details($details);
    if ($mode === 'callback' && !valid_phone($details['phone'] ?? '')) {
        throw new InvalidArgumentException('Please enter a valid phone number for a callback.');
    }
    $historyJson = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($historyJson === false) {
        $historyJson = '[]';
    }

    $select = db()->prepare('SELECT * FROM chat_leads WHERE session_key = ? LIMIT 1');
    $select->execute([$sessionKey]);
    $existing = $select->fetch() ?: null;
    $reference = $existing['reference'] ?? generate_reference();
    $merged = [];
    foreach (['name','email','phone','vehicle_make','vehicle_model','vehicle_reg','address','postcode','current_location','destination','problem','required_time','service','distance_miles'] as $key) {
        $merged[$key] = $details[$key] ?? ($existing[$key] ?? '');
    }
    $handoverMessage = chat_handover_message($reference, $merged);
    $status = $mode === 'callback' ? 'callback_requested' : 'handover_requested';
    $channel = $mode === 'callback' ? 'Callback' : 'WhatsApp';

    if ($existing) {
        db()->prepare(
            'UPDATE chat_leads SET name=?, email=?, phone=?, vehicle_make=?, vehicle_model=?, vehicle_reg=?, address=?, postcode=?, current_location=?, destination=?, problem=?, required_time=?, service=?, distance_miles=?, conversation_json=?, handover_message=?, status=?, handover_channel=?, handover_requested_at=NOW() WHERE id=?'
        )->execute([
            $merged['name'], $merged['email'], $merged['phone'], $merged['vehicle_make'], $merged['vehicle_model'],
            $merged['vehicle_reg'], $merged['address'], $merged['postcode'], $merged['current_location'], $merged['destination'],
            $merged['problem'], $merged['required_time'], $merged['service'], $merged['distance_miles'], $historyJson,
            $handoverMessage, $status, $channel, (int) $existing['id'],
        ]);
        $leadId = (int) $existing['id'];
    } else {
        db()->prepare(
            'INSERT INTO chat_leads (session_key, reference, name, email, phone, vehicle_make, vehicle_model, vehicle_reg, address, postcode, current_location, destination, problem, required_time, service, distance_miles, conversation_json, handover_message, status, handover_channel, handover_requested_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $sessionKey, $reference, $merged['name'], $merged['email'], $merged['phone'], $merged['vehicle_make'], $merged['vehicle_model'],
            $merged['vehicle_reg'], $merged['address'], $merged['postcode'], $merged['current_location'], $merged['destination'],
            $merged['problem'], $merged['required_time'], $merged['service'], $merged['distance_miles'], $historyJson,
            $handoverMessage, $status, $channel,
        ]);
        $leadId = (int) db()->lastInsertId();
    }

    if (!$existing) {
        chat_handover_add_event($leadId, 'chat_started', 'Website chat started', 'Website');
    }
    if ($merged['name'] !== '' || $merged['phone'] !== '' || $merged['vehicle_reg'] !== '' || $merged['current_location'] !== '' || $merged['problem'] !== '') {
        chat_handover_add_event($leadId, 'details_captured', 'Recovery details captured', 'Website');
    }
    $eventType = $mode === 'callback' ? 'callback_requested' : 'human_handover_requested';
    $eventMessage = $mode === 'callback' ? 'Customer requested a callback' : 'Customer requested human assistance';
    chat_handover_add_event($leadId, $eventType, $eventMessage, $channel);
    if ($mode !== 'callback') {
        chat_handover_add_event($leadId, 'whatsapp_initiated', 'Handover to WhatsApp initiated', 'WhatsApp');
    }

    $detailLines = chat_handover_detail_lines($merged);
    $plainDetails = $detailLines ? implode("\n", $detailLines) : 'No customer details were supplied yet.';
    $alertTitle = $mode === 'callback' ? 'CALLBACK REQUESTED' : 'WHATSAPP HUMAN HANDOVER';
    $alert = "🟢 {$alertTitle}\n\n" . $plainDetails . "\nReference: {$reference}\n\n" . ($mode === 'callback'
        ? 'The customer has asked MancWay Recovery to call them back.'
        : 'Customer is moving from the website chatbot to WhatsApp.');
    try {
        db()->prepare('INSERT INTO messages (name, email, phone, subject, message, is_read, ip) VALUES (?,?,?,?,?,0,?)')->execute([
            $merged['name'] !== '' ? $merged['name'] : 'Website chat visitor',
            $merged['email'], $merged['phone'], $alertTitle . ' — ' . $reference, $alert, client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('MancWay chat handover could not create CRM alert: ' . $e->getMessage());
    }
    $htmlDetails = $detailLines ? '<ul><li>' . implode('</li><li>', array_map('e', $detailLines)) . '</li></ul>' : '<p>No customer details were supplied yet.</p>';
    send_site_email($alertTitle . ' — ' . $reference, '<h2>🟢 ' . e($alertTitle) . '</h2><p><strong>Reference:</strong> ' . e($reference) . '</p>' . $htmlDetails . '<p>' . e($mode === 'callback' ? 'The customer has requested a callback.' : 'Customer is moving from the website chatbot to WhatsApp.') . '</p>', $merged['email']);

    return [
        'lead_id' => $leadId,
        'reference' => $reference,
        'whatsapp_message' => $handoverMessage,
        'whatsapp_url' => chat_handover_whatsapp_url($handoverMessage),
        'phone' => chat_handover_phone(),
        'phone_url' => chat_handover_phone_href(),
        'status' => $status,
    ];
}

function chat_handover_add_event(int $leadId, string $type, string $message, string $channel = ''): void
{
    db()->prepare('INSERT INTO chat_lead_events (lead_id, event_type, message, channel) VALUES (?,?,?,?)')->execute([$leadId, $type, $message, $channel]);
}

/** @return array<int,array<string,mixed>> */
function chat_handover_recent_leads(int $limit = 20): array
{
    ensure_chat_handover_schema();
    $limit = max(1, min(100, $limit));
    return db()->query('SELECT * FROM chat_leads ORDER BY updated_at DESC LIMIT ' . $limit)->fetchAll();
}

/** @return array<int,array<string,mixed>> */
function chat_handover_events(int $leadId): array
{
    ensure_chat_handover_schema();
    $stmt = db()->prepare('SELECT * FROM chat_lead_events WHERE lead_id=? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$leadId]);
    return $stmt->fetchAll();
}
