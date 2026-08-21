<?php
declare(strict_types=1);

/**
 * Pricing, invoices and Stripe helpers.
 *
 * Stripe credentials are intentionally read from environment/config or the
 * integration_secrets table only. They are never sent to the browser.
 */

function payment_default_method(): string
{
    return setting('payment_method_default', 'stripe') === 'bank_transfer' ? 'bank_transfer' : 'stripe';
}

function stripe_secret_key(): string
{
    $configured = trim((string)cfg('STRIPE_SECRET_KEY', ''));
    if ($configured !== '' && !str_contains($configured, 'CHANGE_ME') && !str_contains($configured, 'PASTE_')) {
        return $configured;
    }
    return trim(integration_secret('stripe_secret_key', ''));
}

function stripe_publishable_key(): string
{
    $configured = trim((string)cfg('STRIPE_PUBLISHABLE_KEY', ''));
    if ($configured !== '' && !str_contains($configured, 'CHANGE_ME') && !str_contains($configured, 'PASTE_')) {
        return $configured;
    }
    return trim(integration_secret('stripe_publishable_key', ''));
}

function stripe_webhook_secret(): string
{
    $configured = trim((string)cfg('STRIPE_WEBHOOK_SECRET', ''));
    if ($configured !== '' && !str_contains($configured, 'CHANGE_ME') && !str_contains($configured, 'PASTE_')) {
        return $configured;
    }
    return trim(integration_secret('stripe_webhook_secret', ''));
}

function stripe_is_configured(): bool
{
    return stripe_secret_key() !== '' && function_exists('curl_init');
}

/** Ensure the payment columns/tables exist on older production databases. */
function ensure_payment_schema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS invoices (
            id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id                INT UNSIGNED NOT NULL,
            invoice_number            VARCHAR(24) NOT NULL,
            public_token              CHAR(64) NOT NULL,
            invoice_type              ENUM('deposit','balance','full') NOT NULL DEFAULT 'deposit',
            description               VARCHAR(255) NOT NULL DEFAULT '',
            subtotal                  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            amount_due                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency                  CHAR(3) NOT NULL DEFAULT 'GBP',
            payment_method            ENUM('stripe','bank_transfer') NOT NULL DEFAULT 'stripe',
            status                    ENUM('draft','sent','paid','void','failed') NOT NULL DEFAULT 'draft',
            stripe_payment_link_id    VARCHAR(100) NOT NULL DEFAULT '',
            stripe_payment_link_url   TEXT,
            stripe_checkout_session_id VARCHAR(100) NOT NULL DEFAULT '',
            stripe_payment_intent_id  VARCHAR(100) NOT NULL DEFAULT '',
            stripe_error              TEXT,
            bank_reference            VARCHAR(60) NOT NULL DEFAULT '',
            email_sent_at             DATETIME NULL,
            paid_at                   DATETIME NULL,
            created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            UNIQUE KEY public_token (public_token),
            KEY booking_id (booking_id),
            KEY status (status),
            KEY stripe_payment_link_id (stripe_payment_link_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columns = [
        'distance_miles' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER vehicle_reg",
        'quoted_total' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER distance_miles",
        'deposit_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER quoted_total",
        'deposit_status' => "ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid' AFTER deposit_amount",
        'balance_status' => "ENUM('not_due','unpaid','paid') NOT NULL DEFAULT 'not_due' AFTER deposit_status",
    ];
    foreach ($columns as $column => $definition) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE bookings ADD COLUMN `' . $column . '` ' . $definition);
        }
    }
    $ready = true;
}

function booking_base_price_for_service(string $slug, float $fallback = 0.0): float
{
    $overrides = [
        'breakdown-recovery' => 50.00,
        'accident-recovery'  => 120.00,
        'vehicle-transport'  => 120.00,
    ];
    return round((float)($overrides[$slug] ?? $fallback), 2);
}

/** @return array{base:float,miles:float,mile_rate:float,total:float,deposit:float,balance:float} */
function booking_quote_for_service(string $slug, float $miles = 0.0, float $fallback = 0.0): array
{
    $miles = max(0.0, round($miles, 2));
    $base = booking_base_price_for_service($slug, $fallback);
    $mileRate = 2.50;
    $total = round($base + ($miles * $mileRate), 2);
    $deposit = 50.00;
    return [
        'base' => $base,
        'miles' => $miles,
        'mile_rate' => $mileRate,
        'total' => $total,
        'deposit' => $deposit,
        'balance' => max(0.00, round($total - $deposit, 2)),
    ];
}

function invoice_type_label(string $type): string
{
    return [
        'deposit' => 'Deposit',
        'balance' => 'Balance',
        'full' => 'Full amount',
    ][$type] ?? ucfirst($type);
}

function invoice_status_label(string $status): string
{
    return [
        'draft' => 'Draft',
        'sent' => 'Awaiting payment',
        'paid' => 'Paid',
        'void' => 'Void',
        'failed' => 'Payment link failed',
    ][$status] ?? ucfirst($status);
}

function invoice_number(): string
{
    return 'MWI-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function invoice_public_url(array $invoice): string
{
    return url('/invoice.php?number=' . rawurlencode((string)$invoice['invoice_number']) . '&token=' . rawurlencode((string)$invoice['public_token']));
}

/** @return array<string,string> */
function payment_bank_details(): array
{
    return [
        'account_name' => trim((string)setting('bank_account_name', '')),
        'bank_name' => trim((string)setting('bank_name', '')),
        'sort_code' => trim((string)setting('bank_sort_code', '')),
        'account_number' => trim((string)setting('bank_account_number', '')),
    ];
}

function payment_bank_ready(): bool
{
    $bank = payment_bank_details();
    return $bank['account_name'] !== '' && $bank['sort_code'] !== '' && $bank['account_number'] !== '';
}

/** @return array{ok:bool,data:array<string,mixed>,error:string} */
function stripe_api_post(string $path, array $params): array
{
    if (!stripe_is_configured()) {
        return ['ok' => false, 'data' => [], 'error' => 'Stripe is not configured.'];
    }
    $curl = curl_init('https://api.stripe.com' . $path);
    if ($curl === false) {
        return ['ok' => false, 'data' => [], 'error' => 'Could not initialise Stripe.'];
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERPWD => stripe_secret_key() . ':',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($body === false || $curlError !== '' || !is_array($data)) {
        return ['ok' => false, 'data' => [], 'error' => 'Stripe returned an invalid response.'];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'data' => $data, 'error' => (string)($data['error']['message'] ?? 'Stripe rejected the request.')];
    }
    return ['ok' => true, 'data' => $data, 'error' => ''];
}

/** @return array{ok:bool,id:string,url:string,error:string} */
function stripe_create_payment_link(array $invoice, array $booking): array
{
    $amountPence = (int)round(((float)$invoice['amount_due']) * 100);
    $description = invoice_type_label((string)$invoice['invoice_type']) . ' for booking ' . $booking['reference'];
    $params = [
        'line_items[0][price_data][currency]' => 'gbp',
        'line_items[0][price_data][unit_amount]' => $amountPence,
        'line_items[0][price_data][product_data][name]' => 'MancWay Recovery — Invoice ' . $invoice['invoice_number'],
        'line_items[0][price_data][product_data][description]' => $description,
        'line_items[0][quantity]' => 1,
        'metadata[invoice_id]' => (string)$invoice['id'],
        'metadata[invoice_number]' => (string)$invoice['invoice_number'],
        'metadata[booking_id]' => (string)$booking['id'],
        'after_completion[type]' => 'hosted_confirmation',
        'after_completion[hosted_confirmation][custom_message]' => 'Payment received. MancWay Recovery will confirm your recovery request.',
        'billing_address_collection' => 'auto',
    ];
    $result = stripe_api_post('/v1/payment_links', $params);
    if (!$result['ok']) {
        return ['ok' => false, 'id' => '', 'url' => '', 'error' => $result['error']];
    }
    return [
        'ok' => true,
        'id' => (string)($result['data']['id'] ?? ''),
        'url' => (string)($result['data']['url'] ?? ''),
        'error' => '',
    ];
}

/** @return array<string,mixed>|null */
function get_invoice(int $invoiceId): ?array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT i.*, b.reference, b.name, b.email, b.phone, b.vehicle_reg, b.address, b.postcode, b.quoted_total, b.distance_miles, s.title AS service_title
        FROM invoices i JOIN bookings b ON b.id=i.booking_id LEFT JOIN services s ON s.id=b.service_id WHERE i.id=? LIMIT 1');
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** @return array<string,mixed>|null */
function get_invoice_by_public_reference(string $number, string $token): ?array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT i.*, b.reference, b.name, b.email, b.phone, b.vehicle_reg, b.address, b.postcode, b.quoted_total, b.distance_miles, s.title AS service_title
        FROM invoices i JOIN bookings b ON b.id=i.booking_id LEFT JOIN services s ON s.id=b.service_id WHERE i.invoice_number=? AND i.public_token=? LIMIT 1');
    $stmt->execute([$number, $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Create the automatic £50 deposit invoice once per booking. */
function create_booking_deposit_invoice(int $bookingId): ?array
{
    ensure_payment_schema();
    $existing = db()->prepare("SELECT id FROM invoices WHERE booking_id=? AND invoice_type='deposit' AND status <> 'void' ORDER BY id DESC LIMIT 1");
    $existing->execute([$bookingId]);
    $existingId = (int)$existing->fetchColumn();
    if ($existingId > 0) {
        return get_invoice($existingId);
    }
    return create_invoice_for_booking($bookingId, 'deposit', 50.00, '');
}

/** @return array<string,mixed>|null */
function create_invoice_for_booking(int $bookingId, string $type = 'deposit', float $amount = 0.0, string $method = ''): ?array
{
    ensure_payment_schema();
    $bookingStmt = db()->prepare('SELECT b.*, s.slug AS service_slug, s.title AS service_title, s.price_from FROM bookings b LEFT JOIN services s ON s.id=b.service_id WHERE b.id=? LIMIT 1');
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch();
    if (!$booking) {
        return null;
    }
    if (!in_array($type, ['deposit', 'balance', 'full'], true)) {
        $type = 'deposit';
    }
    $quote = booking_quote_for_service((string)($booking['service_slug'] ?? ''), (float)($booking['distance_miles'] ?? 0), (float)($booking['price_from'] ?? 0));
    $subtotal = $quote['total'];
    if ($type === 'deposit') {
        $amountDue = 50.00;
    } elseif ($amount > 0) {
        $amountDue = round($amount, 2);
    } elseif ($type === 'balance') {
        $amountDue = $quote['balance'];
    } else {
        $amountDue = $subtotal;
    }
    if ($amountDue <= 0) {
        return null;
    }
    $method = $method !== '' ? $method : payment_default_method();
    if (!in_array($method, ['stripe', 'bank_transfer'], true)) {
        $method = payment_default_method();
    }
    if ($method === 'stripe' && !stripe_is_configured()) {
        $method = 'bank_transfer';
    }
    $description = invoice_type_label($type) . ' for booking ' . $booking['reference'];
    $invoiceNumber = invoice_number();
    $token = bin2hex(random_bytes(32));
    $status = $method === 'bank_transfer' ? 'sent' : 'draft';
    $insert = db()->prepare('INSERT INTO invoices
        (booking_id, invoice_number, public_token, invoice_type, description, subtotal, amount_due, currency, payment_method, status, bank_reference, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
    $insert->execute([$bookingId, $invoiceNumber, $token, $type, $description, $subtotal, $amountDue, 'GBP', $method, $status, $invoiceNumber]);
    $invoiceId = (int)db()->lastInsertId();
    $invoice = get_invoice($invoiceId);

    if ($method === 'stripe' && $invoice) {
        $link = stripe_create_payment_link($invoice, $booking);
        if ($link['ok'] && $link['id'] !== '' && $link['url'] !== '') {
            db()->prepare('UPDATE invoices SET status=?, stripe_payment_link_id=?, stripe_payment_link_url=?, stripe_error=NULL WHERE id=?')
                ->execute(['sent', $link['id'], $link['url'], $invoiceId]);
        } else {
            db()->prepare('UPDATE invoices SET status=?, stripe_error=? WHERE id=?')->execute(['failed', $link['error'], $invoiceId]);
        }
    }
    $invoice = get_invoice($invoiceId);
    if ($invoice && in_array($invoice['status'], ['sent', 'paid'], true) && (string)$booking['email'] !== '') {
        send_invoice_email($invoiceId);
    }
    return get_invoice($invoiceId);
}

function invoice_email_body(array $invoice): string
{
    $paymentUrl = $invoice['payment_method'] === 'stripe' ? (string)$invoice['stripe_payment_link_url'] : invoice_public_url($invoice);
    $body = '<h2>MancWay Recovery invoice ' . e($invoice['invoice_number']) . '</h2>';
    $body .= '<p>Hello ' . e($invoice['name']) . ',</p>';
    $body .= '<p>Please find your ' . e(strtolower(invoice_type_label((string)$invoice['invoice_type']))) . ' for booking <strong>' . e($invoice['reference']) . '</strong>.</p>';
    $body .= '<p><strong>Description:</strong> ' . e($invoice['description']) . '<br><strong>Amount due:</strong> ' . e(format_price($invoice['amount_due'])) . '</p>';
    if ($invoice['payment_method'] === 'stripe' && $paymentUrl !== '') {
        $body .= '<p><a href="' . e($paymentUrl) . '" style="display:inline-block;padding:12px 18px;background:#f5a623;color:#0b1f3a;text-decoration:none;font-weight:700;border-radius:6px">Pay securely with Stripe</a></p>';
    } elseif ($invoice['payment_method'] === 'bank_transfer') {
        $bank = payment_bank_details();
        $body .= '<p><strong>Bank transfer details</strong><br>Account name: ' . e($bank['account_name'] ?: 'To be confirmed') . '<br>Bank: ' . e($bank['bank_name'] ?: 'To be confirmed') . '<br>Sort code: ' . e($bank['sort_code'] ?: 'To be confirmed') . '<br>Account number: ' . e($bank['account_number'] ?: 'To be confirmed') . '<br>Reference: ' . e($invoice['bank_reference']) . '</p>';
        $body .= '<p>View the invoice online: <a href="' . e(invoice_public_url($invoice)) . '">' . e(invoice_public_url($invoice)) . '</a></p>';
    } else {
        $body .= '<p>We could not create the online payment link yet. Please contact ' . e(site_phone()) . ' so we can issue a new payment option.</p>';
    }
    $body .= '<p>If you have any questions, call ' . e(site_phone()) . '. We will confirm the booking separately by phone.</p>';
    return $body;
}

function send_invoice_email(int $invoiceId): bool
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice || !valid_email((string)$invoice['email'])) {
        return false;
    }
    $sent = send_customer_email((string)$invoice['email'], 'MancWay Recovery invoice ' . $invoice['invoice_number'], invoice_email_body($invoice));
    if ($sent) {
        db()->prepare('UPDATE invoices SET email_sent_at=NOW() WHERE id=?')->execute([$invoiceId]);
    }
    return $sent;
}

function mark_invoice_paid(int $invoiceId, string $checkoutSessionId = '', string $paymentIntentId = ''): bool
{
    ensure_payment_schema();
    $invoice = get_invoice($invoiceId);
    if (!$invoice || $invoice['status'] === 'void') {
        return false;
    }
    db()->prepare('UPDATE invoices SET status=?, paid_at=COALESCE(paid_at,NOW()), stripe_checkout_session_id=COALESCE(NULLIF(?,\'\'),stripe_checkout_session_id), stripe_payment_intent_id=COALESCE(NULLIF(?,\'\'),stripe_payment_intent_id) WHERE id=?')
        ->execute(['paid', $checkoutSessionId, $paymentIntentId, $invoiceId]);
    if ($invoice['invoice_type'] === 'deposit') {
        db()->prepare("UPDATE bookings SET deposit_status='paid', deposit_amount=50.00 WHERE id=?")->execute([(int)$invoice['booking_id']]);
    } elseif ($invoice['invoice_type'] === 'balance') {
        db()->prepare("UPDATE bookings SET balance_status='paid' WHERE id=?")->execute([(int)$invoice['booking_id']]);
    } elseif ($invoice['invoice_type'] === 'full') {
        db()->prepare("UPDATE bookings SET deposit_status='paid', balance_status='paid' WHERE id=?")->execute([(int)$invoice['booking_id']]);
    }
    return true;
}
