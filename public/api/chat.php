<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

/** @param array<string,mixed> $payload */
function chat_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chat_csrf_valid(string $token): bool
{
    return $token !== '' && !empty($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token);
}

/** @param array<int,array<string,mixed>> $services */
function chat_fallback_reply(string $message, array $services): string
{
    $text = mb_strtolower($message);
    $phone = site_phone();
    $serviceLines = [];
    foreach ($services as $service) {
        $serviceLines[] = $service['title'] . ' from ' . format_price(booking_base_price_for_service((string)$service['slug'], (float)$service['price_from']));
    }

    if (preg_match('/\b(book|booking|recover me|send someone|need help|breakdown|stranded|won.t start|won.t move)\b/i', $text)) {
        return 'I can take the essentials for a booking here. Tap “Book a recovery” below and our team will confirm the job by phone. For urgent help, call ' . $phone . '.';
    }
    if (preg_match('/\b(price|cost|how much|quote|charge|expensive)\b/i', $text)) {
        $summary = $serviceLines ? implode('; ', $serviceLines) : 'pricing depends on the vehicle and distance';
        return 'Our guide prices are Breakdown £50, Accident £120 and Long-Distance Transport £120, plus £2.50 per mile. Every booking has a £50 deposit. We confirm the exact cost before dispatch. Current service guide: ' . $summary . '. For an accurate quote, tell us the pickup postcode, vehicle registration and where it needs to go.';
    }
    if (preg_match('/\b(area|where|cover|postcode|salford|stockport|bolton|bury|oldham|rochdale|tameside|trafford|wigan)\b/i', $text)) {
        return 'MancWay Recovery covers Greater Manchester, including Manchester, Salford, Trafford, Stockport, Tameside, Bury, Bolton, Rochdale, Oldham and Wigan, plus longer-distance transport. Tell me your postcode and I’ll point you to the right next step.';
    }
    if (preg_match('/\b(reg|registration|vehicle|car|van|motorbike|dvla)\b/i', $text)) {
        return 'Your registration is enough to start a request. On the booking form, enter it at the top and tap “Find details”; DVLA-held details will be filled in where available, and you can edit them before sending.';
    }
    if (preg_match('/\b(phone|call|contact|email|address|located)\b/i', $text)) {
        return 'You can call MancWay Recovery on ' . $phone . '. Our base address is ' . setting('address', 'Upper Cyrus St, Manchester M40 7FD') . '. We are available 24/7 for recovery enquiries.';
    }
    if (preg_match('/\b(accident|crash|collision|danger|injur|unsafe|motorway)\b/i', $text)) {
        return 'If anyone is hurt or the location is unsafe, call 999 first and move to a safe place if you can. Once safe, call ' . $phone . ' or use “Book a recovery” below and tell us what has happened.';
    }

    return 'I can help with breakdown, accident, specialist recovery and vehicle transport across Greater Manchester. Ask me about prices, coverage or your vehicle, or tap “Book a recovery” to send your details.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    chat_json_response(['ok' => false, 'message' => 'Use POST to send a chat message.'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    chat_json_response(['ok' => false, 'message' => 'That message could not be read. Please try again.'], 400);
}

if (!chat_csrf_valid((string)($payload['csrf_token'] ?? ''))) {
    chat_json_response(['ok' => false, 'message' => 'Your session has expired. Refresh the page and try again.'], 419);
}

$message = trim((string)($payload['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 1200) {
    chat_json_response(['ok' => false, 'message' => 'Please enter a short message and try again.'], 422);
}

// Keep an accidentally exposed or heavily-used chat session from consuming
// the configured provider allowance. This is deliberately session-based so a
// genuine visitor is not blocked by another visitor's traffic.
$now = time();
$windowStart = (int)($_SESSION['chat_window_start'] ?? 0);
$windowCount = (int)($_SESSION['chat_window_count'] ?? 0);
if ($windowStart < $now - 3600) {
    $windowStart = $now;
    $windowCount = 0;
}
if ($windowCount >= 40) {
    chat_json_response(['ok' => false, 'message' => 'Please call the recovery team directly so we can help you.'], 429);
}
$_SESSION['chat_window_start'] = $windowStart;
$_SESSION['chat_window_count'] = $windowCount + 1;

$services = [];
try {
    $services = db()->query('SELECT slug, title, price_from FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
} catch (Throwable $e) {
    error_log('MancWay chat could not load services.');
}

$history = [];
if (isset($payload['history']) && is_array($payload['history'])) {
    foreach (array_slice($payload['history'], -10) as $item) {
        if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true)) {
            continue;
        }
        $content = trim((string)($item['content'] ?? ''));
        if ($content !== '' && mb_strlen($content) <= 1800) {
            $history[] = ['role' => $item['role'], 'content' => $content];
        }
    }
}

$phone = site_phone();
$actions = [
    ['type' => 'booking', 'label' => 'Book a recovery'],
    ['type' => 'call', 'label' => 'Call ' . $phone, 'href' => 'tel:' . setting('phone_href', $phone)],
];
$fallback = chat_fallback_reply($message, $services);
$apiKey = deepseek_api_key();

if ($apiKey === '' || !function_exists('curl_init')) {
    chat_json_response(['ok' => true, 'reply' => $fallback, 'actions' => $actions, 'ai' => false]);
}

$serviceContext = 'No service prices are currently available.';
if ($services) {
    $serviceContext = implode('; ', array_map(static function (array $service): string {
        return $service['title'] . ' from ' . format_price(booking_base_price_for_service((string)$service['slug'], (float)$service['price_from']));
    }, $services));
}
$system = 'You are the friendly online assistant for MancWay Recovery, a UK vehicle recovery business. '
    . 'Answer in concise, warm British English. The business provides breakdown recovery, accident recovery, specialist recovery and vehicle transport across Greater Manchester and beyond. '
    . 'It is available 24/7. The business address is ' . setting('address', 'Upper Cyrus St, Manchester M40 7FD') . '. '
    . 'The phone number is ' . site_phone() . '. Current service guide: ' . $serviceContext . '. '
    . 'Pricing rules are Breakdown Recovery £50 base, Accident Recovery £120 base and Long-Distance Vehicle Transport £120 base, all plus £2.50 per estimated mile. Every booking requires a £50 deposit, with the balance invoiced after confirmation. '
    . 'Help visitors understand services, coverage, vehicle registration lookup, what details are needed and the next step. '
    . 'Never say a booking is confirmed: the website can collect a booking request, and the recovery team confirms it by phone. '
    . 'For immediate danger or injury, tell the visitor to call 999 first. Do not invent availability, exact quotes, arrival times or DVLA data. '
    . 'When a visitor wants to book, encourage the quick booking button or the full booking page. Keep responses under 100 words unless a short list is genuinely useful.';

$messages = [['role' => 'system', 'content' => $system]];
foreach ($history as $item) {
    $messages[] = $item;
}
$messages[] = ['role' => 'user', 'content' => $message];
$requestBody = json_encode([
    'model' => deepseek_model(),
    'messages' => $messages,
    'max_tokens' => 420,
    'temperature' => 0.35,
    'stream' => false,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$curl = curl_init(DEEPSEEK_API_URL . '/chat/completions');
if ($curl === false || $requestBody === false) {
    chat_json_response(['ok' => true, 'reply' => $fallback, 'actions' => $actions, 'ai' => false]);
}
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
]);
$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $curlError !== '' || $httpStatus < 200 || $httpStatus >= 300) {
    error_log('MancWay DeepSeek chat request failed.');
    chat_json_response(['ok' => true, 'reply' => $fallback, 'actions' => $actions, 'ai' => false]);
}

$response = json_decode($responseBody, true);
$reply = $response['choices'][0]['message']['content'] ?? '';
if (!is_string($reply) || trim($reply) === '') {
    error_log('MancWay DeepSeek chat returned no assistant message.');
    chat_json_response(['ok' => true, 'reply' => $fallback, 'actions' => $actions, 'ai' => false]);
}

chat_json_response(['ok' => true, 'reply' => trim($reply), 'actions' => $actions, 'ai' => true]);
