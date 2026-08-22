<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

/** @param array<string,mixed> $payload */
function dvla_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    dvla_json_response([
        'ok' => false,
        'message' => 'Use POST to look up a vehicle registration.',
    ], 405);
}

if (!csrf_verify()) {
    dvla_json_response([
        'ok' => false,
        'message' => 'Your session has expired. Refresh the page and try again.',
    ], 419);
}

$rawRegistration = trim((string)($_POST['registrationNumber'] ?? ''));
$registration = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', $rawRegistration));

if ($registration === '' || strlen($registration) > 8) {
    dvla_json_response([
        'ok' => false,
        'message' => 'Enter a valid vehicle registration, for example AB12 CDE.',
    ], 422);
}

$dvlaKey = dvla_api_key();
if ($dvlaKey === '' || str_contains($dvlaKey, 'PASTE_') || str_contains($dvlaKey, 'CHANGE_ME')) {
    dvla_json_response([
        'ok' => false,
        'code' => 'not_configured',
        'message' => 'Vehicle lookup is not configured yet. You can still enter the vehicle details manually.',
    ], 503);
}

if (!function_exists('curl_init')) {
    error_log('MancWay DVLA lookup unavailable: PHP cURL extension is not enabled.');
    dvla_json_response([
        'ok' => false,
        'message' => 'Vehicle lookup is temporarily unavailable. Please enter the details manually.',
    ], 503);
}

$requestBody = json_encode(['registrationNumber' => $registration], JSON_UNESCAPED_SLASHES);
if ($requestBody === false) {
    dvla_json_response([
        'ok' => false,
        'message' => 'Vehicle lookup is temporarily unavailable. Please enter the details manually.',
    ], 503);
}

$curl = curl_init(DVLA_API_URL);
if ($curl === false) {
    error_log('MancWay DVLA lookup unavailable: could not initialise PHP cURL.');
    dvla_json_response([
        'ok' => false,
        'message' => 'Vehicle lookup is temporarily unavailable. Please enter the details manually.',
    ], 503);
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'x-api-key: ' . $dvlaKey,
        'X-Correlation-Id: mancway-' . bin2hex(random_bytes(8)),
    ],
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $curlError !== '') {
    error_log('MancWay DVLA lookup failed: upstream request error.');
    dvla_json_response([
        'ok' => false,
        'message' => 'Vehicle lookup is temporarily unavailable. Please enter the details manually.',
    ], 503);
}

$data = json_decode($responseBody, true);
if (!is_array($data)) {
    error_log('MancWay DVLA lookup failed: invalid upstream response.');
    dvla_json_response([
        'ok' => false,
        'message' => 'Vehicle lookup is temporarily unavailable. Please enter the details manually.',
    ], 502);
}

if ($httpStatus === 404) {
    dvla_json_response([
        'ok' => false,
        'code' => 'not_found',
        'message' => 'No vehicle was found for that registration. Check it and try again.',
    ], 404);
}

if ($httpStatus === 400 || $httpStatus === 422) {
    dvla_json_response([
        'ok' => false,
        'code' => 'invalid_registration',
        'message' => 'That registration could not be checked. Check it and try again.',
    ], 422);
}

if ($httpStatus !== 200) {
    error_log('MancWay DVLA lookup failed: upstream HTTP status ' . $httpStatus . '.');
    dvla_json_response([
        'ok' => false,
        'message' => 'Vehicle lookup is temporarily unavailable. Please enter the details manually.',
    ], 503);
}

$vehicle = [];
$stringFields = [
    'registrationNumber',
    'make',
    'model',
    'colour',
    'fuelType',
    'taxStatus',
    'motStatus',
    'euroStatus',
];
foreach ($stringFields as $field) {
    if (isset($data[$field]) && is_scalar($data[$field])) {
        $value = trim((string)$data[$field]);
        if ($value !== '') {
            $vehicle[$field] = $value;
        }
    }
}

foreach (['yearOfManufacture', 'engineCapacity'] as $field) {
    if (isset($data[$field]) && is_numeric($data[$field])) {
        $vehicle[$field] = (int)$data[$field];
    }
}

$vehicle['registrationNumber'] = $vehicle['registrationNumber'] ?? $registration;

dvla_json_response([
    'ok' => true,
    'vehicle' => $vehicle,
    'message' => 'Vehicle details found.',
]);
