<?php

function loadEnv(string $file): array {
    if (!is_readable($file)) {
        exit("Missing .env file\n");
    }

    $settings = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $settings[trim($key)] = trim($value);
        }
    }

    return $settings;
}

function request(string $url, array $options): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        exit("Network error: $error\n");
    }

    curl_close($ch);
    return [$status, $body];
}

$cfg = loadEnv(dirname(__DIR__) . '/.env');

foreach ([
    'DARAJA_CONSUMER_KEY',
    'DARAJA_CONSUMER_SECRET',
    'DARAJA_SHORTCODE',
    'DARAJA_PASSKEY',
] as $name) {
    if (empty($cfg[$name])) {
        exit("Configure $name in .env first\n");
    }
}

$phone = $argv[1] ?? '';
$amount = $argv[2] ?? '';

if (!preg_match('/^254[17][0-9]{8}$/', $phone)) {
    exit("Usage: php src/stkpush.php 2547XXXXXXXX 1\nUse a sandbox-supported test number.\n");
}

if (!ctype_digit($amount) || (int)$amount < 1) {
    exit("Amount must be a positive whole number in KSh.\n");
}

$timestamp = date('YmdHis');
$shortcode = $cfg['DARAJA_SHORTCODE'];
$password = base64_encode(
    $shortcode . $cfg['DARAJA_PASSKEY'] . $timestamp
);

$oauth = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

[$status, $body] = request($oauth, [
    CURLOPT_USERPWD => $cfg['DARAJA_CONSUMER_KEY'] . ':' .
                       $cfg['DARAJA_CONSUMER_SECRET'],
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
]);

$tokenData = json_decode($body, true);

if ($status !== 200 || empty($tokenData['access_token'])) {
    exit("Could not authenticate with Daraja (HTTP $status): $body\n");
}

$callback = $cfg['DARAJA_CALLBACK_URL'] ?? '';

if (!filter_var($callback, FILTER_VALIDATE_URL) ||
    !str_starts_with($callback, 'https://')) {
    exit("Set DARAJA_CALLBACK_URL to a public HTTPS callback URL first.\n");
}

$payload = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => (int)$amount,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callback,
    'AccountReference' => 'TestPayment',
    'TransactionDesc' => 'STK Push sandbox test',
];

[$status, $body] = request(
    'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
    [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $tokenData['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]
);

echo "HTTP status: $status\n";
echo "Safaricom response:\n$body\n";
