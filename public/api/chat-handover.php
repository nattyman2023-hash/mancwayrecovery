<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

/** @param array<string,mixed> $payload */
function chat_handover_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    chat_handover_json_response(['ok' => false, 'message' => 'Use POST to save a chat handover.'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    chat_handover_json_response(['ok' => false, 'message' => 'That handover could not be read. Please try again.'], 400);
}

$csrf = (string) ($payload['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
    chat_handover_json_response(['ok' => false, 'message' => 'Your session has expired. Refresh the page and try again.'], 419);
}

$windowStart = (int) ($_SESSION['handover_window_start'] ?? 0);
$windowCount = (int) ($_SESSION['handover_window_count'] ?? 0);
$now = time();
if ($windowStart < $now - 3600) {
    $windowStart = $now;
    $windowCount = 0;
}
if ($windowCount >= 10) {
    chat_handover_json_response(['ok' => false, 'message' => 'Please call the recovery team directly so we can help you.'], 429);
}
$_SESSION['handover_window_start'] = $windowStart;
$_SESSION['handover_window_count'] = $windowCount + 1;

$mode = (string) ($payload['mode'] ?? 'whatsapp');
if (!in_array($mode, ['whatsapp', 'callback'], true)) {
    $mode = 'whatsapp';
}

$details = chat_handover_clean_details($payload['collected'] ?? []);
if ($mode === 'callback') {
    $callbackPhone = chat_handover_clean_value($payload['callback_phone'] ?? '', 30);
    $callbackName = chat_handover_clean_value($payload['callback_name'] ?? '', 120);
    if ($callbackPhone !== '') {
        $details['phone'] = $callbackPhone;
    }
    if ($callbackName !== '') {
        $details['name'] = $callbackName;
    }
}
// WhatsApp handover must remain available even when the visitor has not yet
// supplied contact details. Any name, email, phone or recovery information
// already captured is still persisted and included in the CRM lead/message.
// Callback requests are validated by chat_handover_save() because a phone
// number is required for that channel.
$history = chat_handover_clean_history($payload['history'] ?? []);
$sessionKey = chat_handover_clean_value($payload['session_key'] ?? '', 64);

try {
    $result = chat_handover_save($details, $history, $sessionKey, $mode);
    chat_handover_json_response([
        'ok' => true,
        'reference' => $result['reference'],
        'lead_id' => $result['lead_id'],
        'status' => $result['status'],
        'whatsapp_url' => $result['whatsapp_url'],
        'phone' => $result['phone'],
        'phone_url' => $result['phone_url'],
        'message' => $mode === 'callback'
            ? 'Thanks — your details are saved and the MancWay Recovery team will call you back.'
            : 'Your details are saved under reference ' . $result['reference'] . '. WhatsApp is ready with the information you have already supplied.',
    ]);
} catch (InvalidArgumentException $e) {
    chat_handover_json_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('MancWay chat handover failed: ' . $e->getMessage());
    chat_handover_json_response(['ok' => false, 'message' => 'We could not save the handover just now. Please call 07480 255634.'], 500);
}
