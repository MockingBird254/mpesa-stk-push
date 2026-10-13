<?php
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['Result' => 'Invalid JSON']);
    exit;
}

$callback = $data['Body']['stkCallback'] ?? null;

if (!is_array($callback)) {
    http_response_code(400);
    echo json_encode(['Result' => 'Invalid callback format']);
    exit;
}

$record = [
    'received_at' => date(DATE_ATOM),
    'merchant_request_id' => $callback['MerchantRequestID'] ?? null,
    'checkout_request_id' => $callback['CheckoutRequestID'] ?? null,
    'result_code' => $callback['ResultCode'] ?? null,
    'result_description' => $callback['ResultDesc'] ?? null,
];

if (isset($callback['CallbackMetadata']['Item'])) {
    foreach ($callback['CallbackMetadata']['Item'] as $item) {
        if (isset($item['Name'])) {
            $record['metadata'][$item['Name']] = $item['Value'] ?? null;
        }
    }
}

file_put_contents(
    dirname(__DIR__) . '/logs/callbacks.jsonl',
    json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

echo json_encode(['Result' => 'Accepted']);
