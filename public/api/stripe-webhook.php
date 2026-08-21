<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$webhookSecret = stripe_webhook_secret();
$payload = (string)file_get_contents('php://input');
$signatureHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if ($webhookSecret === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Stripe webhook signing secret is not configured.']);
    exit;
}

// Verify Stripe's signed payload before reading or updating any invoice.
$timestamp = 0;
$signatures = [];
foreach (explode(',', $signatureHeader) as $part) {
    [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($key === 't') $timestamp = (int)$value;
    if ($key === 'v1' && $value !== '') $signatures[] = $value;
}
$expected = $timestamp > 0 ? hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret) : '';
$validSignature = $expected !== '' && abs(time() - $timestamp) <= 300;
if ($validSignature) {
    $validSignature = false;
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            $validSignature = true;
            break;
        }
    }
}
if (!$validSignature) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid Stripe signature.']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid event payload.']);
    exit;
}

$type = (string)($event['type'] ?? '');
$object = $event['data']['object'] ?? [];
if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && is_array($object)) {
    $invoiceId = (int)($object['metadata']['invoice_id'] ?? 0);
    $paymentLinkId = (string)($object['payment_link'] ?? '');
    if ($invoiceId <= 0 && $paymentLinkId !== '') {
        $lookup = db()->prepare('SELECT id FROM invoices WHERE stripe_payment_link_id=? LIMIT 1');
        $lookup->execute([$paymentLinkId]);
        $invoiceId = (int)$lookup->fetchColumn();
    }
    $paid = $type === 'checkout.session.async_payment_succeeded' || (string)($object['payment_status'] ?? '') === 'paid';
    if ($invoiceId > 0 && $paid) {
        mark_invoice_paid($invoiceId, (string)($object['id'] ?? ''), (string)($object['payment_intent'] ?? ''));
    }
}

echo json_encode(['ok' => true, 'received' => true]);
