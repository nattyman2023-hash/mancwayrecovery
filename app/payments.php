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
            booking_id                INT UNSIGNED NULL,
            customer_name             VARCHAR(120) NOT NULL DEFAULT '',
            customer_email            VARCHAR(190) NOT NULL DEFAULT '',
            customer_phone            VARCHAR(30) NOT NULL DEFAULT '',
            customer_address          VARCHAR(255) NOT NULL DEFAULT '',
            invoice_number            VARCHAR(24) NOT NULL,
            public_token              CHAR(64) NOT NULL,
            invoice_type              ENUM('deposit','balance','full','custom') NOT NULL DEFAULT 'deposit',
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

    // Existing installations have the original booking-only invoice shape.
    // Make the relationship optional and add the standalone customer fields.
    $metaStmt = $pdo->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME IN ('booking_id','invoice_type')");
    $invoiceMeta = [];
    foreach ($metaStmt->fetchAll() as $meta) {
        $invoiceMeta[(string)$meta['COLUMN_NAME']] = $meta;
    }
    if (($invoiceMeta['booking_id']['IS_NULLABLE'] ?? 'NO') !== 'YES' || !str_contains((string)($invoiceMeta['invoice_type']['COLUMN_TYPE'] ?? ''), "'custom'")) {
        $pdo->exec("ALTER TABLE invoices MODIFY booking_id INT UNSIGNED NULL, MODIFY invoice_type ENUM('deposit','balance','full','custom') NOT NULL DEFAULT 'deposit'");
    }
    $invoiceColumns = [
        'customer_name' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER booking_id",
        'customer_email' => "VARCHAR(190) NOT NULL DEFAULT '' AFTER customer_name",
        'customer_phone' => "VARCHAR(30) NOT NULL DEFAULT '' AFTER customer_email",
        'customer_address' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER customer_phone",
    ];
    foreach ($invoiceColumns as $column => $definition) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE invoices ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    $columns = [
        'vehicle_year' => "SMALLINT UNSIGNED NULL DEFAULT NULL AFTER vehicle_reg",
        'vehicle_colour' => "VARCHAR(40) NOT NULL DEFAULT '' AFTER vehicle_year",
        'vehicle_fuel' => "VARCHAR(40) NOT NULL DEFAULT '' AFTER vehicle_colour",
        'vehicle_mot' => "VARCHAR(40) NOT NULL DEFAULT '' AFTER vehicle_fuel",
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

    // Enriched invoice fields are added lazily so existing Hostinger installs
    // do not need a destructive re-import before the CRM can use them.
    $invoiceColumns = [
        'company_name' => "VARCHAR(160) NOT NULL DEFAULT ''",
        'customer_reference' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'vehicle_make' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'vehicle_model' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'collection_location' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'destination' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'recovery_date' => "DATE NULL",
        'invoice_date' => "DATE NULL",
        'due_date' => "DATE NULL",
        'discount_type' => "ENUM('none','fixed','percent') NOT NULL DEFAULT 'none'",
        'discount_value' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'discount_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'vat_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'vat_rate' => "DECIMAL(5,2) NOT NULL DEFAULT 0.00",
        'vat_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'total_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'deposit_paid' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'amount_paid' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'payment_terms' => "VARCHAR(255) NOT NULL DEFAULT 'Payment is due upon receipt.'",
        'customer_notes' => "TEXT",
        'internal_notes' => "TEXT",
        'reminders_paused' => "TINYINT(1) NOT NULL DEFAULT 0",
        'created_by' => "INT UNSIGNED NULL",
        'updated_by' => "INT UNSIGNED NULL",
    ];
    foreach ($invoiceColumns as $column => $definition) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE invoices ADD COLUMN `' . $column . '` ' . $definition);
        }
    }
    // Expand, rather than replace, the original enums. Existing values remain
    // valid and new manual payment/status workflows can use their own labels.
    $pdo->exec("ALTER TABLE invoices MODIFY payment_method ENUM('stripe','bank_transfer','cash','card','other') NOT NULL DEFAULT 'stripe', MODIFY status ENUM('draft','sent','viewed','part_paid','paid','overdue','cancelled','refunded','void','failed') NOT NULL DEFAULT 'draft'");
    $pdo->exec("UPDATE invoices SET invoice_date=COALESCE(invoice_date, DATE(created_at)), total_amount=CASE WHEN total_amount=0 THEN GREATEST(subtotal, amount_due) ELSE total_amount END, amount_paid=CASE WHEN status IN ('paid','refunded') AND amount_paid=0 THEN amount_due ELSE amount_paid END WHERE invoice_date IS NULL OR total_amount=0 OR (status IN ('paid','refunded') AND amount_paid=0)");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS invoice_items (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id    INT UNSIGNED NOT NULL,
            sort_order    INT NOT NULL DEFAULT 0,
            description   VARCHAR(255) NOT NULL DEFAULT '',
            quantity      DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            unit_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            vat_rate      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            line_total    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY invoice_id (invoice_id), KEY sort_order (invoice_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS invoice_payments (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id      INT UNSIGNED NOT NULL,
            amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_method  ENUM('stripe','bank_transfer','cash','card','other') NOT NULL DEFAULT 'other',
            paid_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reference       VARCHAR(120) NOT NULL DEFAULT '',
            note            TEXT,
            created_by      INT UNSIGNED NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY invoice_id (invoice_id), KEY paid_at (paid_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS invoice_events (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id   INT UNSIGNED NOT NULL,
            event_type   VARCHAR(60) NOT NULL,
            description  VARCHAR(255) NOT NULL DEFAULT '',
            metadata_json LONGTEXT,
            created_by   INT UNSIGNED NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY invoice_id (invoice_id), KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Backfill a legacy one-line invoice only once, without changing its
    // financial meaning.
    $legacyItems = $pdo->query("SELECT i.id, i.description, i.amount_due FROM invoices i LEFT JOIN invoice_items ii ON ii.invoice_id=i.id WHERE ii.id IS NULL")->fetchAll();
    if ($legacyItems) {
        $itemInsert = $pdo->prepare('INSERT INTO invoice_items (invoice_id, sort_order, description, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)');
        foreach ($legacyItems as $legacy) {
            $amount = (float)$legacy['amount_due'];
            $itemInsert->execute([(int)$legacy['id'], 1, (string)($legacy['description'] ?: 'MancWay Recovery service'), 1, $amount, $amount]);
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

/** @return array<int,array<string,mixed>> */
function normalise_invoice_items($items): array
{
    if (!is_array($items)) {
        return [];
    }
    $clean = [];
    foreach (array_values($items) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $description = trim((string)($item['description'] ?? ''));
        $quantity = round((float)($item['quantity'] ?? 1), 2);
        $unitPrice = round((float)($item['unit_price'] ?? $item['unitPrice'] ?? 0), 2);
        $vatRate = round((float)($item['vat_rate'] ?? 0), 2);
        if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
            continue;
        }
        $quantity = min($quantity, 100000);
        $unitPrice = min($unitPrice, 10000000);
        $vatRate = max(0, min($vatRate, 100));
        $clean[] = [
            'description' => function_exists('mb_substr') ? mb_substr($description, 0, 255) : substr($description, 0, 255),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate' => $vatRate,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
        if (count($clean) >= 100) {
            break;
        }
    }
    return $clean;
}

/** @param array<int,array<string,mixed>> $items */
function calculate_invoice_totals(array $items, string $discountType = 'none', float $discountValue = 0, bool $vatEnabled = false, float $vatRate = 0, float $depositPaid = 0): array
{
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float)($item['line_total'] ?? ((float)$item['quantity'] * (float)$item['unit_price']));
    }
    $subtotal = round($subtotal, 2);
    if (!in_array($discountType, ['none', 'fixed', 'percent'], true)) {
        $discountType = 'none';
    }
    $discountValue = max(0, round($discountValue, 2));
    $discountAmount = $discountType === 'percent'
        ? round($subtotal * min($discountValue, 100) / 100, 2)
        : ($discountType === 'fixed' ? min($discountValue, $subtotal) : 0.0);
    $taxable = max(0, round($subtotal - $discountAmount, 2));
    $vatRate = max(0, min(round($vatRate, 2), 100));
    $vatAmount = $vatEnabled ? round($taxable * $vatRate / 100, 2) : 0.0;
    $total = round($taxable + $vatAmount, 2);
    $depositPaid = max(0, min(round($depositPaid, 2), $total));
    return [
        'subtotal' => $subtotal,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'discount_amount' => $discountAmount,
        'vat_enabled' => $vatEnabled ? 1 : 0,
        'vat_rate' => $vatRate,
        'vat_amount' => $vatAmount,
        'total_amount' => $total,
        'deposit_paid' => $depositPaid,
        'amount_due' => max(0, round($total - $depositPaid, 2)),
    ];
}

/** @return array<int,array<string,mixed>> */
function get_invoice_items(int $invoiceId): array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

function invoice_event(int $invoiceId, string $type, string $description, array $metadata = []): void
{
    ensure_payment_schema();
    $json = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    try {
        db()->prepare('INSERT INTO invoice_events (invoice_id, event_type, description, metadata_json, created_by) VALUES (?,?,?,?,?)')
            ->execute([$invoiceId, $type, $description, $json, (int)($_SESSION['admin_id'] ?? 0) ?: null]);
    } catch (Throwable $e) {
        error_log('MancWay invoice event could not be recorded: ' . $e->getMessage());
    }
}

/** @return array<int,array<string,mixed>> */
function get_invoice_events(int $invoiceId): array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT * FROM invoice_events WHERE invoice_id=? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

/** @return array<int,array<string,mixed>> */
function get_invoice_payments(int $invoiceId): array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT * FROM invoice_payments WHERE invoice_id=? ORDER BY paid_at ASC, id ASC');
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

function invoice_type_label(string $type): string
{
    return [
        'deposit' => 'Deposit',
        'balance' => 'Balance',
        'full' => 'Full amount',
        'custom' => 'Invoice',
    ][$type] ?? ucfirst($type);
}

function invoice_status_label(string $status): string
{
    return [
        'draft' => 'Draft',
        'sent' => 'Awaiting payment',
        'viewed' => 'Viewed',
        'part_paid' => 'Part paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'void' => 'Void',
        'failed' => 'Payment link failed',
    ][$status] ?? ucfirst($status);
}

function invoice_number(): string
{
    ensure_payment_schema();
    $year = date('Y');
    $stmt = db()->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) FROM invoices WHERE invoice_number LIKE ?");
    $stmt->execute(['MW-INV-' . $year . '-%']);
    $next = ((int)$stmt->fetchColumn()) + 1;
    return sprintf('MW-INV-%s-%05d', $year, $next);
}

function invoice_public_url(array $invoice): string
{
    return url('/invoice?number=' . rawurlencode((string)$invoice['invoice_number']) . '&token=' . rawurlencode((string)$invoice['public_token']));
}

function invoice_pdf_escape(string $value): string
{
    $value = str_replace('£', chr(163), $value);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($converted !== false) $value = $converted;
    }
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
}

function invoice_pdf_text(float $x, float $y, float $size, string $text, bool $bold = false): string
{
    $font = $bold ? '/F2' : '/F1';
    return 'BT ' . $font . ' ' . rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.') . ' Tf ' . rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.') . ' ' . rtrim(rtrim(number_format($y, 2, '.', ''), '0'), '.') . ' Td (' . invoice_pdf_escape($text) . ') Tj ET';
}

/** Create a real, printable A4 PDF without requiring a Composer package. */
function invoice_pdf_bytes(array $invoice, ?array $items = null, ?array $payments = null): string
{
    $items = $items ?? get_invoice_items((int)$invoice['id']);
    $payments = $payments ?? get_invoice_payments((int)$invoice['id']);
    $pages = [];
    $page = [];
    $y = 735.0;
    $newPage = function () use (&$pages, &$page, &$y): void {
        if ($page) $pages[] = $page;
        $page = [];
        $y = 735.0;
    };
    $addText = function (float $x, float $atY, float $size, string $text, bool $bold = false) use (&$page): void {
        $page[] = invoice_pdf_text($x, $atY, $size, $text, $bold);
    };
    $addRule = function (float $atY, string $colour = '0.85 0.88 0.92') use (&$page): void {
        $page[] = 'q ' . $colour . ' rg 40 ' . $atY . ' 515 0.7 re f Q';
    };
    $startPage = function (bool $continuation = false) use (&$page, &$y, $addText, $addRule): void {
        $page[] = 'q 0.96 0.65 0.14 rg 40 778 515 3 re f Q';
        $addText(40, 748, 19, 'MancWay Recovery', true);
        $addText(40, 731, 8.5, site_email() . '  |  ' . site_phone());
        $addText(410, 748, 8, $continuation ? 'Invoice continuation' : 'INVOICE', true);
        $addRule(718, '0.10 0.18 0.29');
        $y = 700;
    };
    $startPage();
    $addText(40, $y, 12, (string)$invoice['invoice_number'], true);
    $addText(420, $y, 8.5, 'Invoice date: ' . ($invoice['invoice_date'] ?? date('Y-m-d')));
    $addText(420, $y - 14, 8.5, 'Due date: ' . ($invoice['due_date'] ?? ($invoice['invoice_date'] ?? date('Y-m-d'))));
    $y -= 34;
    $addText(40, $y, 8, 'BILL TO', true);
    $addText(300, $y, 8, 'RECOVERY DETAILS', true);
    $y -= 16;
    $customer = trim((string)($invoice['name'] ?? $invoice['customer_name'] ?? 'Customer'));
    if (!empty($invoice['company_name'])) $customer .= ' (' . $invoice['company_name'] . ')';
    $addText(40, $y, 9.5, $customer, true);
    $addText(300, $y, 8.5, 'Job reference: ' . ((string)($invoice['reference'] ?? '') ?: 'Standalone'));
    $y -= 13;
    foreach (array_filter([(string)($invoice['address'] ?? ''), (string)($invoice['email'] ?? ''), (string)($invoice['phone'] ?? '')]) as $line) {
        $addText(40, $y, 8.5, $line);
        $y -= 12;
    }
    $recoveryLines = array_filter([
        !empty($invoice['display_vehicle_make']) || !empty($invoice['display_vehicle_model']) ? 'Vehicle: ' . trim(($invoice['display_vehicle_make'] ?? '') . ' ' . ($invoice['display_vehicle_model'] ?? '')) : '',
        !empty($invoice['display_vehicle_reg']) ? 'Registration: ' . $invoice['display_vehicle_reg'] : '',
        !empty($invoice['display_collection_location']) ? 'Collection: ' . $invoice['display_collection_location'] : '',
        !empty($invoice['display_destination']) ? 'Destination: ' . $invoice['display_destination'] : '',
        !empty($invoice['display_recovery_date']) ? 'Recovery date: ' . $invoice['display_recovery_date'] : '',
    ]);
    $detailY = $y + 13 * count(array_filter([(string)($invoice['address'] ?? ''), (string)($invoice['email'] ?? ''), (string)($invoice['phone'] ?? '')]));
    foreach ($recoveryLines as $line) {
        $addText(300, $detailY, 8.5, $line);
        $detailY -= 12;
    }
    $y = min($y, $detailY) - 20;
    $addRule($y + 8);
    $addText(40, $y - 8, 8, 'DESCRIPTION', true);
    $addText(345, $y - 8, 8, 'QTY', true);
    $addText(395, $y - 8, 8, 'UNIT', true);
    $addText(475, $y - 8, 8, 'TOTAL', true);
    $y -= 26;
    foreach ($items as $item) {
        if ($y < 82) {
            $addText(40, 52, 8, 'Continued on next page');
            $newPage(true);
            $startPage(true);
            $addText(40, $y - 8, 8, 'DESCRIPTION', true);
            $addText(345, $y - 8, 8, 'QTY', true);
            $addText(395, $y - 8, 8, 'UNIT', true);
            $addText(475, $y - 8, 8, 'TOTAL', true);
            $y -= 26;
        }
        $description = (string)($item['description'] ?? 'Recovery service');
        if (function_exists('mb_strimwidth')) $description = mb_strimwidth($description, 0, 48, '…');
        else $description = substr($description, 0, 48);
        $addText(40, $y, 8.5, $description);
        $addText(345, $y, 8.5, number_format((float)$item['quantity'], 2));
        $addText(395, $y, 8.5, format_price((float)$item['unit_price']));
        $addText(475, $y, 8.5, format_price((float)$item['line_total']), true);
        $y -= 15;
    }
    if ($y < 180) {
        $addText(40, 52, 8, 'Continued on next page');
        $newPage(true);
        $startPage(true);
    }
    $y -= 8;
    $addRule($y + 8);
    $addText(350, $y - 10, 8.5, 'Subtotal');
    $addText(480, $y - 10, 8.5, format_price((float)$invoice['subtotal']), true);
    $y -= 15;
    if ((float)($invoice['discount_amount'] ?? 0) > 0) {
        $addText(350, $y - 10, 8.5, 'Discount');
        $addText(480, $y - 10, 8.5, '-' . format_price((float)$invoice['discount_amount']));
        $y -= 15;
    }
    if (!empty($invoice['vat_enabled']) && (float)($invoice['vat_amount'] ?? 0) > 0) {
        $addText(350, $y - 10, 8.5, 'VAT (' . (float)$invoice['vat_rate'] . '%)');
        $addText(480, $y - 10, 8.5, format_price((float)$invoice['vat_amount']));
        $y -= 15;
    }
    if ((float)($invoice['deposit_paid'] ?? 0) > 0) {
        $addText(350, $y - 10, 8.5, 'Deposit paid');
        $addText(480, $y - 10, 8.5, '-' . format_price((float)$invoice['deposit_paid']));
        $y -= 15;
    }
    $addRule($y - 2, '0.10 0.18 0.29');
    $addText(350, $y - 18, 11, 'Balance due', true);
    $addText(465, $y - 18, 11, format_price((float)$invoice['amount_due']), true);
    $y -= 46;
    if ($payments) {
        $addText(40, $y, 8, 'PAYMENT HISTORY', true);
        $y -= 14;
        foreach ($payments as $payment) {
            if ($y < 70) { $newPage(true); $startPage(true); }
            $addText(40, $y, 8.5, date('d M Y', strtotime((string)$payment['paid_at'])) . ' · ' . invoice_payment_method_label((string)$payment['payment_method']));
            $addText(470, $y, 8.5, format_price((float)$payment['amount']), true);
            $y -= 13;
        }
        $y -= 8;
    }
    if ((string)($invoice['payment_terms'] ?? '') !== '') {
        if ($y < 90) { $newPage(true); $startPage(true); }
        $addText(40, $y, 8, 'PAYMENT TERMS', true);
        $addText(40, $y - 13, 8.5, (string)$invoice['payment_terms']);
        $y -= 30;
    }
    if ((string)($invoice['customer_notes'] ?? '') !== '') {
        $addText(40, $y, 8, 'NOTE', true);
        $addText(40, $y - 13, 8.5, (string)$invoice['customer_notes']);
    }
    $page[] = invoice_pdf_text(40, 38, 7.5, 'MancWay Recovery · ' . site_email() . ' · ' . site_phone());
    if ($page) $pages[] = $page;

    // Reserve object 1 for the catalog and object 2 for the page tree.
    $objects = ['', '', ''];
    $addObject = static function (string $object) use (&$objects): int { $objects[] = $object; return count($objects) - 1; };
    $fontRegular = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $fontBold = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
    $imageId = 0;
    $logoPath = APP_DIR . '/../public/assets/img/logo.jpeg';
    if (is_file($logoPath) && function_exists('getimagesize')) {
        $size = @getimagesize($logoPath);
        $logo = @file_get_contents($logoPath);
        if ($size && $logo !== false) {
            $imageId = $addObject('<< /Type /XObject /Subtype /Image /Width ' . (int)$size[0] . ' /Height ' . (int)$size[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo) . " >>\nstream\n" . $logo . "\nendstream");
        }
    }
    $pageIds = [];
    foreach ($pages as $contentLines) {
        if ($imageId) array_unshift($contentLines, 'q 80 0 0 38 40 782 cm /Im1 Do Q');
        $content = implode("\n", $contentLines);
        $contentId = $addObject('<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream");
        $resources = '/Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >>';
        if ($imageId) $resources .= ' /XObject << /Im1 ' . $imageId . ' 0 R >>';
        $pageIds[] = $addObject('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << ' . $resources . ' >> /Contents ' . $contentId . ' 0 R >>');
    }
    $kids = implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds));
    $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    for ($i = 1; $i < count($objects); $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . count($objects) . "\n0000000000 65535 f \n";
    for ($i = 1; $i < count($objects); $i++) $pdf .= sprintf('%010d 00000 n \n', $offsets[$i]);
    $pdf .= "trailer\n<< /Size " . count($objects) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
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
    $amountPence = (int)round(max(0, ((float)$invoice['amount_due'] - (float)($invoice['amount_paid'] ?? 0))) * 100);
    if ($amountPence <= 0) return ['ok' => false, 'id' => '', 'url' => '', 'error' => 'There is no outstanding balance for this invoice.'];
    $reference = trim((string)($booking['reference'] ?? ''));
    $description = invoice_type_label((string)$invoice['invoice_type']) . ($reference !== '' ? ' for booking ' . $reference : ' from MancWay Recovery');
    $params = [
        'line_items[0][price_data][currency]' => 'gbp',
        'line_items[0][price_data][unit_amount]' => $amountPence,
        'line_items[0][price_data][product_data][name]' => 'MancWay Recovery — Invoice ' . $invoice['invoice_number'],
        'line_items[0][price_data][product_data][description]' => $description,
        'line_items[0][quantity]' => 1,
        'metadata[invoice_id]' => (string)$invoice['id'],
        'metadata[invoice_number]' => (string)$invoice['invoice_number'],
        'after_completion[type]' => 'hosted_confirmation',
        'after_completion[hosted_confirmation][custom_message]' => 'Payment received. MancWay Recovery will confirm your recovery request.',
        'billing_address_collection' => 'auto',
    ];
    if ((int)($booking['id'] ?? 0) > 0) {
        $params['metadata[booking_id]'] = (string)$booking['id'];
    }
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

/** Add optional booking context without making secure invoice lookup depend on joins. */
function invoice_with_context(?array $row): ?array
{
    if (!$row) return null;
    $row['reference'] = '';
    $row['name'] = (string)($row['customer_name'] ?? '');
    $row['email'] = (string)($row['customer_email'] ?? '');
    $row['phone'] = (string)($row['customer_phone'] ?? '');
    $row['address'] = (string)($row['customer_address'] ?? '');
    $row['display_vehicle_make'] = (string)($row['vehicle_make'] ?? '');
    $row['display_vehicle_model'] = (string)($row['vehicle_model'] ?? '');
    $row['display_vehicle_reg'] = (string)($row['vehicle_reg'] ?? '');
    $row['display_collection_location'] = (string)($row['collection_location'] ?? '');
    $row['display_destination'] = (string)($row['destination'] ?? '');
    $row['display_recovery_date'] = (string)($row['recovery_date'] ?? '');
    $row['service_title'] = '';
    if ((int)($row['booking_id'] ?? 0) > 0) {
        $stmt = db()->prepare('SELECT b.reference,b.name,b.email,b.phone,b.address,b.postcode,b.vehicle_make,b.vehicle_model,b.vehicle_reg,b.quoted_total,b.distance_miles,b.preferred_date,s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id WHERE b.id=? LIMIT 1');
        $stmt->execute([(int)$row['booking_id']]);
        $booking = $stmt->fetch() ?: [];
        $row['reference'] = (string)($booking['reference'] ?? '');
        $row['name'] = (string)($row['customer_name'] ?: ($booking['name'] ?? ''));
        $row['email'] = (string)($row['customer_email'] ?: ($booking['email'] ?? ''));
        $row['phone'] = (string)($row['customer_phone'] ?: ($booking['phone'] ?? ''));
        $row['address'] = (string)($row['customer_address'] ?: trim(($booking['address'] ?? '') . ', ' . ($booking['postcode'] ?? ''), ' ,'));
        $row['display_vehicle_make'] = (string)($row['vehicle_make'] ?: ($booking['vehicle_make'] ?? ''));
        $row['display_vehicle_model'] = (string)($row['vehicle_model'] ?: ($booking['vehicle_model'] ?? ''));
        $row['display_vehicle_reg'] = (string)($row['vehicle_reg'] ?: ($booking['vehicle_reg'] ?? ''));
        $row['display_collection_location'] = (string)($row['collection_location'] ?: trim(($booking['address'] ?? '') . ', ' . ($booking['postcode'] ?? ''), ' ,'));
        $row['display_recovery_date'] = (string)($row['recovery_date'] ?: ($booking['preferred_date'] ?? ''));
        $row['service_title'] = (string)($booking['service_title'] ?? '');
        $row['quoted_total'] = (float)($booking['quoted_total'] ?? 0);
        $row['distance_miles'] = (float)($booking['distance_miles'] ?? 0);
        $row['vehicle_reg'] = (string)($booking['vehicle_reg'] ?? ($row['vehicle_reg'] ?? ''));
    }
    return $row;
}

/** @return array<string,mixed>|null */
function get_invoice(int $invoiceId): ?array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT * FROM invoices WHERE id=? LIMIT 1');
    $stmt->execute([$invoiceId]);
    return invoice_with_context($stmt->fetch() ?: null);
}

/** @return array<string,mixed>|null */
function get_invoice_by_public_reference(string $number, string $token): ?array
{
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT * FROM invoices WHERE invoice_number=? AND public_token=? LIMIT 1');
    $stmt->execute([$number, $token]);
    return invoice_with_context($stmt->fetch() ?: null);
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

function invoice_payment_method(string $method = ''): string
{
    $method = $method !== '' ? $method : payment_default_method();
    if (!in_array($method, ['stripe', 'bank_transfer', 'cash', 'card', 'other'], true)) {
        $method = payment_default_method();
    }
    return $method === 'stripe' && !stripe_is_configured() ? 'bank_transfer' : $method;
}

function invoice_payment_method_label(string $method): string
{
    return [
        'stripe' => 'Stripe payment link',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'card' => 'Card',
        'other' => 'Other',
    ][$method] ?? ucfirst(str_replace('_', ' ', $method));
}

/**
 * Create a rich invoice while keeping the old booking and standalone entry
 * points compatible with the rest of the site.
 *
 * @param array<string,mixed> $data
 * @param array<int,array<string,mixed>> $items
 */
function create_invoice_from_data(array $data, array $items = [], bool $sendNow = true): ?array
{
    ensure_payment_schema();
    $bookingId = (int)($data['booking_id'] ?? 0);
    $booking = null;
    if ($bookingId > 0) {
        $bookingStmt = db()->prepare('SELECT b.*, s.slug AS service_slug, s.title AS service_title, s.price_from FROM bookings b LEFT JOIN services s ON s.id=b.service_id WHERE b.id=? LIMIT 1');
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch() ?: null;
        if (!$booking) return null;
    }
    $type = (string)($data['invoice_type'] ?? 'custom');
    if (!in_array($type, ['deposit', 'balance', 'full', 'custom'], true)) $type = 'custom';
    $quote = $booking ? booking_quote_for_service((string)($booking['service_slug'] ?? ''), (float)($booking['distance_miles'] ?? 0), (float)($booking['price_from'] ?? 0)) : null;
    if (!$items && $quote) {
        $items = [['description' => (string)($booking['service_title'] ?: invoice_type_label($type)), 'quantity' => 1, 'unit_price' => $quote['total']]];
    }
    if (!$items) {
        $items = [['description' => (string)($data['description'] ?? 'MancWay Recovery service'), 'quantity' => 1, 'unit_price' => (float)($data['amount_due'] ?? $data['amount'] ?? 0)]];
    }
    $items = normalise_invoice_items($items);
    if ($quote && (!$items || array_sum(array_column($items, 'line_total')) <= 0)) {
        $items = normalise_invoice_items([['description' => (string)($booking['service_title'] ?: invoice_type_label($type)), 'quantity' => 1, 'unit_price' => $quote['total']]]);
    }
    if (!$items) return null;
    $depositPaid = (float)($data['deposit_paid'] ?? (($type === 'balance') ? 50.00 : 0.00));
    $totals = calculate_invoice_totals($items, (string)($data['discount_type'] ?? 'none'), (float)($data['discount_value'] ?? 0), !empty($data['vat_enabled']), (float)($data['vat_rate'] ?? setting('vat_rate', '0')), $depositPaid);
    $amountOverride = round((float)($data['amount_due_override'] ?? 0), 2);
    if ($type === 'deposit') {
        $amountDue = $amountOverride > 0 ? $amountOverride : min(50.00, $totals['total_amount']);
        $depositPaid = 0.00;
    } elseif ($amountOverride > 0) {
        $amountDue = $amountOverride;
    } else {
        $amountDue = $totals['amount_due'];
    }
    $amountDue = max(0, round($amountDue, 2));
    $invoiceDate = trim((string)($data['invoice_date'] ?? '')) ?: date('Y-m-d');
    $dueDate = trim((string)($data['due_date'] ?? '')) ?: $invoiceDate;
    $method = invoice_payment_method((string)($data['payment_method'] ?? ''));
    $invoiceNumber = invoice_number();
    $token = bin2hex(random_bytes(32));
    $description = trim((string)($data['description'] ?? '')) ?: ($items[0]['description'] ?? invoice_type_label($type));
    $description = function_exists('mb_substr') ? mb_substr($description, 0, 255) : substr($description, 0, 255);
    $customerName = trim((string)($data['customer_name'] ?? ($booking['name'] ?? '')));
    $customerEmail = trim((string)($data['customer_email'] ?? ($booking['email'] ?? '')));
    $customerPhone = trim((string)($data['customer_phone'] ?? ($booking['phone'] ?? '')));
    $customerAddress = trim((string)($data['customer_address'] ?? ($booking ? trim(($booking['address'] ?? '') . ', ' . ($booking['postcode'] ?? ''), ' ,') : '')));
    if ($customerName === '' || ($customerEmail !== '' && !valid_email($customerEmail))) return null;
    $vehicleMake = trim((string)($data['vehicle_make'] ?? ($booking['vehicle_make'] ?? '')));
    $vehicleModel = trim((string)($data['vehicle_model'] ?? ($booking['vehicle_model'] ?? '')));
    $vehicleReg = trim((string)($data['vehicle_reg'] ?? ($booking['vehicle_reg'] ?? '')));
    $collection = trim((string)($data['collection_location'] ?? ($booking['address'] ?? '')));
    $destination = trim((string)($data['destination'] ?? ''));
    $recoveryDate = trim((string)($data['recovery_date'] ?? ($booking['preferred_date'] ?? '')));
    $adminId = (int)($_SESSION['admin_id'] ?? 0) ?: null;
    $values = [
        $bookingId > 0 ? $bookingId : null, $customerName, trim((string)($data['company_name'] ?? '')), $customerEmail, $customerPhone,
        $customerAddress, trim((string)($data['customer_reference'] ?? '')), $vehicleMake, $vehicleModel, $vehicleReg, $collection,
        $destination, ($recoveryDate !== '' ? $recoveryDate : null), $invoiceNumber, $token, $type, $description,
        $totals['subtotal'], $totals['discount_type'], $totals['discount_value'], $totals['discount_amount'], $totals['vat_enabled'],
        $totals['vat_rate'], $totals['vat_amount'], $totals['total_amount'], $depositPaid, $amountDue, 0.00, 'GBP', $method, 'draft',
        $invoiceNumber, $invoiceDate, $dueDate, trim((string)($data['payment_terms'] ?? 'Payment is due upon receipt.')),
        trim((string)($data['customer_notes'] ?? '')), trim((string)($data['internal_notes'] ?? '')), !empty($data['reminders_paused']) ? 1 : 0, $adminId, $adminId,
    ];
    $columns = 'booking_id,customer_name,company_name,customer_email,customer_phone,customer_address,customer_reference,vehicle_make,vehicle_model,vehicle_reg,collection_location,destination,recovery_date,invoice_number,public_token,invoice_type,description,subtotal,discount_type,discount_value,discount_amount,vat_enabled,vat_rate,vat_amount,total_amount,deposit_paid,amount_due,amount_paid,currency,payment_method,status,bank_reference,invoice_date,due_date,payment_terms,customer_notes,internal_notes,reminders_paused,created_by,updated_by';
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    db()->prepare('INSERT INTO invoices (' . $columns . ') VALUES (' . $placeholders . ')')->execute($values);
    $invoiceId = (int)db()->lastInsertId();
    $itemInsert = db()->prepare('INSERT INTO invoice_items (invoice_id,sort_order,description,quantity,unit_price,vat_rate,line_total) VALUES (?,?,?,?,?,?,?)');
    foreach ($items as $index => $item) $itemInsert->execute([$invoiceId, $index + 1, $item['description'], $item['quantity'], $item['unit_price'], $item['vat_rate'], $item['line_total']]);
    invoice_event($invoiceId, 'created', 'Invoice created', ['invoice_number' => $invoiceNumber]);
    if (!$sendNow) return get_invoice($invoiceId);
    return finalize_invoice($invoiceId, $booking ?: ['id' => 0, 'reference' => '']);
}

/** Create the Stripe link (when selected) and send the customer invoice email. */
function finalize_invoice(int $invoiceId, array $paymentContext = []): ?array
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice) {
        return null;
    }
    if ($invoice['payment_method'] === 'stripe') {
        $link = stripe_create_payment_link($invoice, $paymentContext);
        if ($link['ok'] && $link['id'] !== '' && $link['url'] !== '') {
            db()->prepare('UPDATE invoices SET status=?, stripe_payment_link_id=?, stripe_payment_link_url=?, stripe_error=NULL WHERE id=?')
                ->execute(['sent', $link['id'], $link['url'], $invoiceId]);
            invoice_event($invoiceId, 'payment_link_created', 'Stripe payment link created');
        } else {
            db()->prepare('UPDATE invoices SET status=?, stripe_error=? WHERE id=?')->execute(['failed', $link['error'], $invoiceId]);
            invoice_event($invoiceId, 'payment_link_failed', 'Stripe payment link could not be created', ['error' => $link['error']]);
        }
    } elseif ($invoice['status'] === 'draft') {
        db()->prepare("UPDATE invoices SET status='sent' WHERE id=?")->execute([$invoiceId]);
    }
    $invoice = get_invoice($invoiceId);
    if ($invoice && in_array($invoice['status'], ['sent', 'paid'], true) && valid_email((string)$invoice['email'])) {
        send_invoice_email($invoiceId);
    }
    return get_invoice($invoiceId);
}

/** @return array<string,mixed>|null */
function create_invoice_for_booking(int $bookingId, string $type = 'deposit', float $amount = 0.0, string $method = ''): ?array
{
    if (!in_array($type, ['deposit', 'balance', 'full'], true)) $type = 'deposit';
    return create_invoice_from_data([
        'booking_id' => $bookingId,
        'invoice_type' => $type,
        'amount_due_override' => $amount,
        'payment_method' => $method,
    ], [], true);
}

/** Create a standalone invoice when there is no booking record to attach. */
function create_standalone_invoice(string $name, string $email, string $phone, string $address, string $description, float $amount, string $method = ''): ?array
{
    return create_invoice_from_data([
        'customer_name' => $name,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'customer_address' => $address,
        'description' => $description,
        'amount_due' => $amount,
        'invoice_type' => 'custom',
        'payment_method' => $method,
    ], [], true);
}

/** @param array<string,mixed> $data @param array<int,array<string,mixed>> $items */
function update_invoice_from_data(int $invoiceId, array $data, array $items = []): ?array
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice || in_array((string)$invoice['status'], ['paid', 'void', 'cancelled', 'refunded'], true)) return null;
    $items = normalise_invoice_items($items ?: get_invoice_items($invoiceId));
    if (!$items) return null;
    $depositPaid = (float)($invoice['deposit_paid'] ?? 0);
    $totals = calculate_invoice_totals($items, (string)($data['discount_type'] ?? $invoice['discount_type'] ?? 'none'), (float)($data['discount_value'] ?? $invoice['discount_value'] ?? 0), !empty($data['vat_enabled']) || !empty($invoice['vat_enabled']), (float)($data['vat_rate'] ?? $invoice['vat_rate'] ?? 0), $depositPaid);
    $amountOverride = round((float)($data['amount_due_override'] ?? 0), 2);
    $amountDue = $amountOverride > 0 ? $amountOverride : (($invoice['invoice_type'] === 'deposit') ? min(50.00, $totals['total_amount']) : $totals['amount_due']);
    $fields = [
        'customer_name' => trim((string)($data['customer_name'] ?? $invoice['customer_name'])),
        'company_name' => trim((string)($data['company_name'] ?? $invoice['company_name'])),
        'customer_email' => trim((string)($data['customer_email'] ?? $invoice['customer_email'])),
        'customer_phone' => trim((string)($data['customer_phone'] ?? $invoice['customer_phone'])),
        'customer_address' => trim((string)($data['customer_address'] ?? $invoice['customer_address'])),
        'customer_reference' => trim((string)($data['customer_reference'] ?? $invoice['customer_reference'])),
        'vehicle_make' => trim((string)($data['vehicle_make'] ?? $invoice['vehicle_make'])),
        'vehicle_model' => trim((string)($data['vehicle_model'] ?? $invoice['vehicle_model'])),
        'vehicle_reg' => trim((string)($data['vehicle_reg'] ?? $invoice['vehicle_reg'])),
        'collection_location' => trim((string)($data['collection_location'] ?? $invoice['collection_location'])),
        'destination' => trim((string)($data['destination'] ?? $invoice['destination'])),
        'recovery_date' => trim((string)($data['recovery_date'] ?? $invoice['recovery_date'])) ?: null,
        'invoice_date' => trim((string)($data['invoice_date'] ?? $invoice['invoice_date'])) ?: date('Y-m-d'),
        'due_date' => trim((string)($data['due_date'] ?? $invoice['due_date'])) ?: date('Y-m-d'),
        'description' => trim((string)($data['description'] ?? $invoice['description'])),
        'subtotal' => $totals['subtotal'], 'discount_type' => $totals['discount_type'], 'discount_value' => $totals['discount_value'],
        'discount_amount' => $totals['discount_amount'], 'vat_enabled' => $totals['vat_enabled'], 'vat_rate' => $totals['vat_rate'],
        'vat_amount' => $totals['vat_amount'], 'total_amount' => $totals['total_amount'], 'amount_due' => max(0, round($amountDue, 2)),
        'payment_method' => invoice_payment_method((string)($data['payment_method'] ?? $invoice['payment_method'])),
        'payment_terms' => trim((string)($data['payment_terms'] ?? $invoice['payment_terms'])),
        'customer_notes' => trim((string)($data['customer_notes'] ?? $invoice['customer_notes'])),
        'internal_notes' => trim((string)($data['internal_notes'] ?? $invoice['internal_notes'])),
        'reminders_paused' => !empty($data['reminders_paused']) ? 1 : 0,
        'updated_by' => (int)($_SESSION['admin_id'] ?? 0) ?: null,
    ];
    if ($fields['customer_name'] === '' || ($fields['customer_email'] !== '' && !valid_email($fields['customer_email']))) return null;
    $sets = [];
    $values = [];
    foreach ($fields as $field => $value) { $sets[] = '`' . $field . '`=?'; $values[] = $value; }
    $values[] = $invoiceId;
    db()->prepare('UPDATE invoices SET ' . implode(',', $sets) . ' WHERE id=?')->execute($values);
    db()->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$invoiceId]);
    $itemInsert = db()->prepare('INSERT INTO invoice_items (invoice_id,sort_order,description,quantity,unit_price,vat_rate,line_total) VALUES (?,?,?,?,?,?,?)');
    foreach ($items as $index => $item) $itemInsert->execute([$invoiceId, $index + 1, $item['description'], $item['quantity'], $item['unit_price'], $item['vat_rate'], $item['line_total']]);
    invoice_event($invoiceId, 'edited', 'Invoice details updated');
    return get_invoice($invoiceId);
}

function duplicate_invoice(int $invoiceId): ?array
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice) return null;
    return create_invoice_from_data([
        'booking_id' => (int)$invoice['booking_id'], 'customer_name' => ($invoice['customer_name'] ?: $invoice['name']), 'company_name' => $invoice['company_name'],
        'customer_email' => ($invoice['customer_email'] ?: $invoice['email']), 'customer_phone' => ($invoice['customer_phone'] ?: $invoice['phone']), 'customer_address' => ($invoice['customer_address'] ?: $invoice['address']),
        'customer_reference' => $invoice['customer_reference'], 'vehicle_make' => $invoice['vehicle_make'], 'vehicle_model' => $invoice['vehicle_model'],
        'vehicle_reg' => $invoice['vehicle_reg'], 'collection_location' => $invoice['collection_location'], 'destination' => $invoice['destination'],
        'recovery_date' => $invoice['recovery_date'], 'invoice_type' => $invoice['invoice_type'], 'description' => $invoice['description'],
        'discount_type' => $invoice['discount_type'], 'discount_value' => $invoice['discount_value'], 'vat_enabled' => $invoice['vat_enabled'],
        'vat_rate' => $invoice['vat_rate'], 'payment_method' => $invoice['payment_method'], 'payment_terms' => $invoice['payment_terms'],
        'customer_notes' => $invoice['customer_notes'], 'internal_notes' => $invoice['internal_notes'],
    ], get_invoice_items($invoiceId), false);
}

function invoice_email_body(array $invoice): string
{
    $secureUrl = invoice_public_url($invoice);
    $paymentUrl = $invoice['payment_method'] === 'stripe' ? (string)$invoice['stripe_payment_link_url'] : $secureUrl;
    $body = '<div style="font-family:Arial,sans-serif;color:#0b1f3a;max-width:620px">';
    $body .= '<div style="border-top:5px solid #f5a623;padding:18px 0"><h2 style="margin:0">MancWay Recovery</h2><p style="margin:5px 0;color:#536b87">Invoice ' . e($invoice['invoice_number']) . '</p></div>';
    $body .= '<p>Hello ' . e($invoice['name'] ?: 'there') . ',</p>';
    $body .= $invoice['reference'] !== '' ? '<p>Please find your ' . e(strtolower(invoice_type_label((string)$invoice['invoice_type']))) . ' for booking <strong>' . e($invoice['reference']) . '</strong>.</p>' : '<p>Please find your MancWay Recovery invoice below.</p>';
    $body .= '<table cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse">';
    foreach (get_invoice_items((int)$invoice['id']) as $item) {
        $body .= '<tr><td style="border-bottom:1px solid #e2e8f0">' . e($item['description']) . ' × ' . e((string)$item['quantity']) . '</td><td align="right" style="border-bottom:1px solid #e2e8f0">' . e(format_price($item['line_total'])) . '</td></tr>';
    }
    $body .= '<tr><td><strong>Amount due</strong></td><td align="right"><strong>' . e(format_price($invoice['amount_due'])) . '</strong></td></tr></table>';
    if ($paymentUrl !== '' && in_array($invoice['status'], ['sent', 'part_paid', 'overdue'], true)) $body .= '<p><a href="' . e($paymentUrl) . '" style="display:inline-block;padding:13px 20px;background:#f5a623;color:#0b1f3a;text-decoration:none;font-weight:700;border-radius:6px">Pay / view invoice</a></p>';
    if ($invoice['payment_method'] === 'bank_transfer') {
        $bank = payment_bank_details();
        $body .= '<p><strong>Bank transfer details</strong><br>Account name: ' . e($bank['account_name'] ?: 'To be confirmed') . '<br>Bank: ' . e($bank['bank_name'] ?: 'To be confirmed') . '<br>Sort code: ' . e($bank['sort_code'] ?: 'To be confirmed') . '<br>Account number: ' . e($bank['account_number'] ?: 'To be confirmed') . '<br>Reference: ' . e($invoice['bank_reference']) . '</p>';
    }
    $body .= '<p><a href="' . e($secureUrl . '&download=1') . '">Download PDF invoice</a> · <a href="https://wa.me/' . e(preg_replace('/[^0-9]/', '', chat_handover_phone())) . '?text=' . rawurlencode('Hi MancWay Recovery, I have a question about invoice ' . $invoice['invoice_number']) . '">WhatsApp us</a></p>';
    $body .= '<p style="color:#536b87">' . e((string)($invoice['payment_terms'] ?? 'Payment is due upon receipt.')) . '</p>';
    $body .= '<p>If you have any questions, call ' . e(site_phone()) . ' or email ' . e(site_email()) . '.</p></div>';
    return $body;
}

function send_invoice_email(int $invoiceId): bool
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice || !valid_email((string)$invoice['email'])) {
        return false;
    }
    $pdf = invoice_pdf_bytes($invoice);
    $attachments = $pdf !== '' ? [[
        'filename' => $invoice['invoice_number'] . '.pdf',
        'content' => base64_encode($pdf),
        'content_type' => 'application/pdf',
    ]] : [];
    $sent = send_customer_email((string)$invoice['email'], 'MancWay Recovery Invoice ' . $invoice['invoice_number'], invoice_email_body($invoice), $attachments);
    if ($sent) {
        db()->prepare('UPDATE invoices SET email_sent_at=NOW() WHERE id=?')->execute([$invoiceId]);
        invoice_event($invoiceId, 'emailed', 'Invoice emailed to ' . (string)$invoice['email']);
    }
    return $sent;
}

function invoice_target_amount(array $invoice): float
{
    $target = (float)($invoice['amount_due'] ?? 0);
    return max(0, round($target, 2));
}

function record_invoice_payment(int $invoiceId, float $amount, string $method = 'other', string $paidAt = '', string $reference = '', string $note = ''): bool
{
    ensure_payment_schema();
    $invoice = get_invoice($invoiceId);
    if (!$invoice || in_array((string)$invoice['status'], ['void', 'cancelled', 'refunded'], true)) return false;
    $remaining = max(0, round(invoice_target_amount($invoice) - (float)($invoice['amount_paid'] ?? 0), 2));
    $amount = min($remaining, max(0, round($amount, 2)));
    if ($amount <= 0) return false;
    if (!in_array($method, ['stripe', 'bank_transfer', 'cash', 'card', 'other'], true)) $method = 'other';
    $date = trim($paidAt) !== '' ? trim($paidAt) : date('Y-m-d H:i:s');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date .= ' 12:00:00';
    db()->prepare('INSERT INTO invoice_payments (invoice_id,amount,payment_method,paid_at,reference,note,created_by) VALUES (?,?,?,?,?,?,?)')
        ->execute([$invoiceId, $amount, $method, $date, trim($reference), trim($note), (int)($_SESSION['admin_id'] ?? 0) ?: null]);
    $newPaid = round((float)($invoice['amount_paid'] ?? 0) + $amount, 2);
    $status = $newPaid >= invoice_target_amount($invoice) ? 'paid' : 'part_paid';
    db()->prepare('UPDATE invoices SET amount_paid=?, status=? WHERE id=?')->execute([$newPaid, $status, $invoiceId]);
    if ($status === 'paid') {
        db()->prepare('UPDATE invoices SET paid_at=COALESCE(paid_at,NOW()) WHERE id=?')->execute([$invoiceId]);
    }
    invoice_event($invoiceId, 'payment_recorded', 'Payment recorded: ' . format_price($amount), ['method' => $method, 'reference' => trim($reference)]);
    $updated = get_invoice($invoiceId);
    if ($updated && $status === 'paid') {
        if ($updated['invoice_type'] === 'deposit') db()->prepare("UPDATE bookings SET deposit_status='paid', deposit_amount=50.00 WHERE id=?")->execute([(int)$updated['booking_id']]);
        elseif ($updated['invoice_type'] === 'balance') db()->prepare("UPDATE bookings SET balance_status='paid' WHERE id=?")->execute([(int)$updated['booking_id']]);
        elseif ($updated['invoice_type'] === 'full') db()->prepare("UPDATE bookings SET deposit_status='paid', balance_status='paid' WHERE id=?")->execute([(int)$updated['booking_id']]);
    }
    return true;
}

function send_payment_receipt(int $invoiceId): bool
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice || !valid_email((string)$invoice['email']) || (string)$invoice['status'] !== 'paid') return false;
    $payments = get_invoice_payments($invoiceId);
    $body = '<div style="font-family:Arial,sans-serif;color:#0b1f3a"><h2>MancWay Recovery</h2><h3>PAID IN FULL · ' . e($invoice['invoice_number']) . '</h3><p>Hello ' . e($invoice['name'] ?: 'there') . ',</p><p>Thank you. We have received <strong>' . e(format_price((float)$invoice['amount_paid'])) . '</strong> for invoice ' . e($invoice['invoice_number']) . '.</p>';
    if ($payments) {
        $body .= '<ul>';
        foreach ($payments as $payment) $body .= '<li>' . e(date('d M Y', strtotime((string)$payment['paid_at']))) . ' · ' . e(invoice_payment_method_label((string)$payment['payment_method'])) . ' · ' . e(format_price($payment['amount'])) . '</li>';
        $body .= '</ul>';
    }
    $body .= '<p>Your secure invoice record: <a href="' . e(invoice_public_url($invoice)) . '">' . e(invoice_public_url($invoice)) . '</a></p><p>Questions? Call ' . e(site_phone()) . '.</p></div>';
    $pdf = invoice_pdf_bytes($invoice, null, $payments);
    $sent = send_customer_email((string)$invoice['email'], 'Payment receipt · MancWay Recovery ' . $invoice['invoice_number'], $body, [[
        'filename' => $invoice['invoice_number'] . '-receipt.pdf', 'content' => base64_encode($pdf), 'content_type' => 'application/pdf',
    ]]);
    if ($sent) invoice_event($invoiceId, 'receipt_sent', 'Payment receipt emailed to ' . (string)$invoice['email']);
    return $sent;
}

function mark_invoice_paid(int $invoiceId, string $checkoutSessionId = '', string $paymentIntentId = ''): bool
{
    $invoice = get_invoice($invoiceId);
    if (!$invoice) return false;
    $method = ($checkoutSessionId !== '' || $paymentIntentId !== '') ? 'stripe' : 'other';
    $ok = record_invoice_payment($invoiceId, invoice_target_amount($invoice) - (float)($invoice['amount_paid'] ?? 0), $method, '', $paymentIntentId ?: $checkoutSessionId, 'Marked paid');
    if ($ok && ($checkoutSessionId !== '' || $paymentIntentId !== '')) {
        db()->prepare('UPDATE invoices SET stripe_checkout_session_id=COALESCE(NULLIF(?,\'\'),stripe_checkout_session_id), stripe_payment_intent_id=COALESCE(NULLIF(?,\'\'),stripe_payment_intent_id) WHERE id=?')->execute([$checkoutSessionId, $paymentIntentId, $invoiceId]);
    }
    return $ok;
}
