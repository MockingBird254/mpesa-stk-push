<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['message' => 'Use the payment form.']);
}

$data = json_decode(file_get_contents('php://input'), true);
$phone = trim((string)($data['phone'] ?? ''));
$amount = (string)($data['amount'] ?? '');

if (!preg_match('/^254[17][0-9]{8}$/', $phone)) {
    respond(422, ['message' => 'Enter a valid Kenyan phone number.']);
}
if (!preg_match('/^[0-9]{1,7}$/', $amount) || (int)$amount < 1) {
    respond(422, ['message' => 'Enter a valid whole-number amount.']);
}

$command = 'php ' . escapeshellarg(__DIR__ . '/stkpush.php')
         . ' ' . escapeshellarg($phone)
         . ' ' . escapeshellarg($amount)
         . ' 2>&1';

exec($command, $output, $exitCode);
$result = implode("\n", $output);

if (preg_match('/"CheckoutRequestID"\s*:\s*"([^"]+)"/', $result, $m)) {
    respond(200, [
        'message' => 'STK Push request accepted. Check your test phone and callback.',
        'checkoutRequestId' => $m[1]
    ]);
}

respond(502, ['message' => 'STK Push failed. Check your Termux output.']);
