<?php
declare(strict_types=1);

/**
 * General helper functions.
 */

/** HTML-escape a value for output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect and stop. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Redirect while storing one-time flash values in the session. */
function redirect_with(string $path, array $flash): void
{
    foreach ($flash as $k => $v) {
        $_SESSION['_flash'][$k] = $v;
    }
    redirect($path);
}

/** Read + clear a flash value. */
function flash(string $key, $default = null)
{
    if (isset($_SESSION['_flash'][$key])) {
        $v = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $v;
    }
    return $default;
}

/** Repopulate a form field after a failed submission. */
function old(string $key, $default = '')
{
    $val = flash('input_' . $key, $default);
    return e($val);
}

/** Cached site setting from the `settings` table. */
function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = db()->query('SELECT `key`, `value` FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (Throwable $e) {
            // DB not ready yet (e.g. during setup).
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Return the DVLA key without exposing where it came from.
 * A server/environment value wins; the admin setting is a convenient fallback.
 */
function dvla_api_key(): string
{
    $serverKey = trim(DVLA_API_KEY);
    if ($serverKey !== '' && !str_contains($serverKey, 'PASTE_') && !str_contains($serverKey, 'CHANGE_ME')) {
        return $serverKey;
    }
    return trim((string) setting('dvla_api_key', ''));
}

/** Output the brand/business name. */
function site_name(): string
{
    return setting('business_name', 'MancWay Recovery');
}

/** Public business email address. */
function site_email(): string
{
    return trim((string) setting('email', 'info@mancwayrecovery.co.uk'));
}

/** Notification destination: explicit config first, then the admin setting. */
function site_notification_email(): string
{
    $configured = trim(MAIL_TO);
    if ($configured !== '' && valid_email($configured)) {
        return $configured;
    }
    $admin = trim((string) setting('admin_email', site_email()));
    return valid_email($admin) ? $admin : '';
}

/** Keep the From address on the business domain when possible. */
function site_from_email(): string
{
    $configured = trim(MAIL_FROM);
    if ($configured !== '' && valid_email($configured)) {
        return $configured;
    }
    $fallback = site_email();
    if (valid_email($fallback)) {
        return $fallback;
    }
    $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
    return 'no-reply@' . preg_replace('/[^a-z0-9.-]/i', '', (string) $host);
}

/** SEO-friendly, reusable profile copy for each dedicated service area page. */
function area_profile(array $area): array
{
    $profiles = [
        'manchester-city' => [
            'summary' => 'Fast vehicle recovery across Manchester city centre, including Deansgate, Ancoats, the Northern Quarter, Ardwick and the university corridor.',
            'places'  => 'Manchester city centre, Deansgate, Ancoats, Ardwick, Hulme and surrounding M postcodes',
        ],
        'salford' => [
            'summary' => 'Reliable breakdown and accident recovery in Salford, from the city centre and MediaCity to Eccles, Swinton and the surrounding M postcodes.',
            'places'  => 'Salford Quays, MediaCity, Eccles, Swinton, Worsley and surrounding M postcodes',
        ],
        'trafford' => [
            'summary' => 'Professional vehicle recovery across Trafford, including Trafford Park, Stretford, Altrincham, Sale and the roads around the M60.',
            'places'  => 'Trafford Park, Stretford, Sale, Altrincham, Urmston and surrounding M postcodes',
        ],
        'stockport' => [
            'summary' => '24/7 recovery support throughout Stockport, from the town centre and Edgeley to Hazel Grove, Bramhall and the wider SK area.',
            'places'  => 'Stockport, Edgeley, Heaton Moor, Bramhall, Hazel Grove and surrounding SK postcodes',
        ],
        'tameside' => [
            'summary' => 'Vehicle recovery for Tameside drivers in Ashton-under-Lyne, Hyde, Denton, Stalybridge and across the eastern Greater Manchester routes.',
            'places'  => 'Ashton-under-Lyne, Hyde, Denton, Stalybridge, Dukinfield and surrounding M and SK postcodes',
        ],
        'bury' => [
            'summary' => 'Breakdown, accident and transport recovery across Bury, including the town centre, Ramsbottom, Whitefield and the northern Greater Manchester routes.',
            'places'  => 'Bury, Ramsbottom, Whitefield, Prestwich and surrounding BL and M postcodes',
        ],
        'bolton' => [
            'summary' => 'Fast recovery for cars and vans across Bolton, including the town centre, Horwich, Farnworth and the wider BL postcode area.',
            'places'  => 'Bolton, Horwich, Farnworth, Westhoughton, Little Lever and surrounding BL postcodes',
        ],
        'rochdale' => [
            'summary' => 'Trusted vehicle recovery in Rochdale and the surrounding north-east Greater Manchester routes, including Middleton, Heywood and Littleborough.',
            'places'  => 'Rochdale, Middleton, Heywood, Littleborough and surrounding OL and M postcodes',
        ],
        'oldham' => [
            'summary' => 'Roadside and planned vehicle recovery throughout Oldham, including Chadderton, Royton, Lees and the surrounding Pennine routes.',
            'places'  => 'Oldham, Chadderton, Royton, Lees, Failsworth and surrounding OL postcodes',
        ],
        'wigan' => [
            'summary' => 'Vehicle recovery and transport across Wigan, including the town centre, Hindley, Ince, Ashton-in-Makerfield and the wider WN area.',
            'places'  => 'Wigan, Hindley, Ince, Ashton-in-Makerfield, Standish and surrounding WN postcodes',
        ],
    ];

    $fallback = [
        'summary' => 'MancWay Recovery provides dependable breakdown, accident and vehicle transport support across ' . $area['name'] . ' and the surrounding postcodes.',
        'places'  => $area['name'] . ' and surrounding postcodes',
    ];
    return array_merge($fallback, $profiles[$area['slug']] ?? [], [
        'name'      => $area['name'],
        'postcodes' => $area['postcodes'],
        'slug'      => $area['slug'],
    ]);
}

/** Output the primary phone number. */
function site_phone(): string
{
    return setting('phone', '0161 000 0000');
}

/** Generate a URL-safe slug. */
function slugify(string $text): string
{
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? $text;
    $text = trim($text, '-');
    $text = function_exists('iconv')
        ? (iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text)
        : $text;
    $text = strtolower(preg_replace('/[^a-z0-9-]+/i', '', $text));
    return $text !== '' ? $text : 'n-a';
}

function valid_phone(string $phone): bool
{
    $clean = preg_replace('/[\s\-().]/', '', $phone);
    return (bool) preg_match('/^[0-9+]{7,15}$/', $clean);
}

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_postcode(string $postcode): bool
{
    return (bool) preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?\s?[0-9][A-Z]{2}$/i', trim($postcode));
}

/** Build an absolute asset URL, cache-busted by the file's last-modified time. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $url  = APP_URL . '/assets/' . $path;
    $file = APP_ROOT . '/public/assets/' . $path;
    $mtime = is_file($file) ? filemtime($file) : false;
    return $mtime ? $url . '?v=' . $mtime : $url;
}

/** Build an absolute site URL. */
function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/** Output a hidden CSRF field for forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Format a price-from value. */
function format_price($price): string
{
    $price = (float) $price;
    if ($price <= 0) {
        return 'POA';
    }
    return '£' . number_format($price, 0);
}

/** Map a service's stored icon keyword to a display glyph. */
function icon_emoji(string $key): string
{
    $map = [
        'truck'      => '🚚',
        'shield'     => '🛡️',
        'map'        => '🗺️',
        'bike'       => '🏍️',
        'wrench'     => '🔧',
        'cog'        => '⚙️',
        'cogs'       => '⚙️',
        'clipboard'  => '📋',
        'disc'       => '🛞',
        'search'     => '🔍',
        'bolt'       => '🔋',
        'gears'      => '⚙️',
        'tyre'       => '🛞',
    ];
    return $map[$key] ?? '🚗';
}

/** Render a star rating string. */
function render_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

/** Client IP (best-effort). */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/** Render a partial view from app/views/. */
function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $path = APP_DIR . '/views/' . $name . '.php';
    if (is_file($path)) {
        require $path;
    }
}

/** Generate a short, unique booking reference. */
function generate_reference(): string
{
    return 'MW' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/** Send an HTML email through the host's configured mail transport. */
function send_email(string $to, string $subject, string $html_body, string $replyTo = ''): bool
{
    if (!function_exists('mail') || !valid_email($to)) {
        return false;
    }
    $subject = trim(str_replace(["\r", "\n"], '', $subject));
    $replyTo = trim(str_replace(["\r", "\n"], '', $replyTo));
    $from = site_from_email();
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    if ($replyTo !== '' && valid_email($replyTo)) {
        $headers .= "Reply-To: $replyTo\r\n";
    }
    return @mail($to, $subject, $html_body, $headers);
}

/** Send a notification to the configured business inbox. */
function send_site_email(string $subject, string $html_body, string $replyTo = ''): bool
{
    $to = site_notification_email();
    return $to !== '' ? send_email($to, $subject, $html_body, $replyTo) : false;
}

/** Send a customer-facing confirmation when the customer supplied an email. */
function send_customer_email(string $to, string $subject, string $html_body): bool
{
    return send_email($to, $subject, $html_body, site_email());
}

/** Output an inline error message for a form field. */
function field_error(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<span class="field-error">' . e($errors[$key]) . '</span>';
}

/* ===================================================================
 *  CRM helpers — enquiries (bookings) + recovery vehicles
 * =================================================================== */

/** Canonical enquiry status list (CRM workflow). */
function enquiry_statuses(): array
{
    return ['new', 'accepted', 'dispatched', 'complete', 'cancelled'];
}

/** Human label for an enquiry status. */
function enquiry_status_label(string $status): string
{
    return [
        'new'        => 'New',
        'accepted'   => 'Accepted',
        'dispatched' => 'Dispatched',
        'complete'   => 'Completed',
        'cancelled'  => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

/** Material Symbol icon for an enquiry status. */
function enquiry_status_icon(string $status): string
{
    return [
        'new'        => 'inbox',
        'accepted'   => 'task_alt',
        'dispatched' => 'local_shipping',
        'complete'   => 'check_circle',
        'cancelled'  => 'cancel',
    ][$status] ?? 'circle';
}

/** Render a coloured status pill for an enquiry. */
function enquiry_status_pill(string $status): string
{
    $label = e(enquiry_status_label($status));
    $icon  = e(enquiry_status_icon($status));
    return '<span class="pill-status pill-' . e($status) . '"><span class="mw-icon status-icon">' . $icon . '</span>' . $label . '</span>';
}

/** Relative "age" of an enquiry from its created timestamp, e.g. "12m", "3h", "2d". */
function enquiry_age(string $createdAt): string
{
    $ts  = strtotime($createdAt);
    $diff = max(0, time() - $ts);
    if ($diff < 60)        return 'just now';
    if ($diff < 3600)      return floor($diff / 60) . 'm ago';
    if ($diff < 86400)     return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)    return floor($diff / 86400) . 'd ago';
    return date('j M', $ts);
}

/** Recovery vehicle status list. */
function vehicle_statuses(): array
{
    return ['available', 'on_job', 'off_duty'];
}

/** Human label for a vehicle status. */
function vehicle_status_label(string $status): string
{
    return [
        'available' => 'Available',
        'on_job'    => 'On a job',
        'off_duty'  => 'Off duty',
    ][$status] ?? ucfirst($status);
}

/** CSS class for a vehicle status pill. */
function vehicle_pill_class(string $status): string
{
    return [
        'available' => 'available',
        'on_job'    => 'on-job',
        'off_duty'  => 'off-duty',
    ][$status] ?? 'off-duty';
}
