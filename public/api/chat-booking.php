<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

/** @param array<string,mixed> $payload */
function chat_booking_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    chat_booking_json_response(['ok' => false, 'message' => 'Use POST to send a booking request.'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    chat_booking_json_response(['ok' => false, 'message' => 'That booking could not be read. Please try again.'], 400);
}

$csrf = (string)($payload['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    chat_booking_json_response(['ok' => false, 'message' => 'Your session has expired. Refresh the page and try again.'], 419);
}
if (!empty($payload['website'])) {
    chat_booking_json_response(['ok' => false, 'message' => 'Please try again.'], 422);
}

$name = trim((string)($payload['name'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$vehicleReg = strtoupper(trim((string)($payload['vehicle_reg'] ?? '')));
$address = trim((string)($payload['address'] ?? ''));
$postcode = strtoupper(trim((string)($payload['postcode'] ?? '')));
$serviceSlug = trim((string)($payload['service'] ?? ''));
$notes = trim((string)($payload['notes'] ?? ''));
$errors = [];

if ($name === '' || mb_strlen($name) > 120) $errors['name'] = 'Please enter your name.';
if ($email !== '' && !valid_email($email)) $errors['email'] = 'Please enter a valid email address.';
if (!valid_phone($phone)) $errors['phone'] = 'Please enter a valid phone number.';
if ($vehicleReg === '' || mb_strlen($vehicleReg) > 20) $errors['vehicle_reg'] = 'Please enter the vehicle registration.';
if ($address === '') $errors['address'] = 'Please enter the pickup address.';
if (!valid_postcode($postcode)) $errors['postcode'] = 'Please enter a valid UK postcode.';

$serviceId = null;
$serviceName = 'General recovery';
if ($serviceSlug !== '') {
    $serviceStmt = db()->prepare('SELECT id, title FROM services WHERE slug = ? AND is_active = 1 LIMIT 1');
    $serviceStmt->execute([$serviceSlug]);
    $service = $serviceStmt->fetch();
    if ($service) {
        $serviceId = (int)$service['id'];
        $serviceName = (string)$service['title'];
    }
}

if ($errors) {
    chat_booking_json_response(['ok' => false, 'message' => reset($errors), 'errors' => $errors], 422);
}

$reference = generate_reference();
$insert = db()->prepare('INSERT INTO bookings
    (reference, name, email, phone, vehicle_make, vehicle_model, vehicle_reg, service_id, address, postcode, preferred_date, preferred_time, notes, status, ip, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
$insert->execute([
    $reference, $name, $email, $phone, '', '', $vehicleReg, $serviceId, $address, $postcode,
    date('Y-m-d'), 'Chat request', mb_substr($notes, 0, 1000), 'new', client_ip()
]);

$body = '<h2>New chat booking — ' . e($reference) . '</h2>';
$body .= '<p><strong>Service:</strong> ' . e($serviceName) . '<br><strong>Name:</strong> ' . e($name) . '<br>';
$body .= '<strong>Phone:</strong> ' . e($phone) . '<br><strong>Email:</strong> ' . e($email ?: '—') . '<br>';
$body .= '<strong>Vehicle registration:</strong> ' . e($vehicleReg) . '<br><strong>Pickup:</strong> ' . e($address) . ', ' . e($postcode) . '</p>';
$body .= '<p><strong>Notes:</strong><br>' . nl2br(e($notes ?: '—')) . '</p>';
$body .= '<p>This request was collected by the website assistant. Confirm the booking by phone.</p>';
send_site_email('New chat booking ' . $reference . ' — ' . $serviceName, $body, $email);

if ($email !== '') {
    $customerBody = '<h2>Recovery request received</h2><p>Thanks, ' . e($name) . '. We have received your request and our team will contact you to confirm the details.</p>';
    $customerBody .= '<p><strong>Reference:</strong> ' . e($reference) . '<br><strong>Service:</strong> ' . e($serviceName) . '</p>';
    $customerBody .= '<p>If you need us urgently, call <a href="tel:' . e(setting('phone_href', site_phone())) . '">' . e(site_phone()) . '</a>.</p>';
    send_customer_email($email, 'Your MancWay recovery request ' . $reference, $customerBody);
}

chat_booking_json_response([
    'ok' => true,
    'reference' => $reference,
    'message' => 'Thanks — your request has been sent. Our recovery team will contact you to confirm it.',
]);
