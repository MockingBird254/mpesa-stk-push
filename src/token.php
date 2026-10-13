<?php
$envFile = dirname(__DIR__) . '/.env';

if (!is_readable($envFile)) {
    exit("Error: .env file not found.\n");
}

$settings = [];

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (str_contains($line, '=')) {
        [$key, $value] = explode('=', $line, 2);
        $settings[trim($key)] = trim($value);
    }
}

$key = $settings['DARAJA_CONSUMER_KEY'] ?? '';
$secret = $settings['DARAJA_CONSUMER_SECRET'] ?? '';

if ($key === '' || $secret === '') {
    exit("Error: Add your Daraja credentials to .env first.\n");
}

$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $key . ':' . $secret,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    fwrite(STDERR, "Connection error: " . curl_error($ch) . "\n");
    curl_close($ch);
    exit(1);
}

curl_close($ch);

$data = json_decode($response, true);

if ($status !== 200 || empty($data['access_token'])) {
    fwrite(STDERR, "Authentication failed (HTTP $status).\n");
    fwrite(STDERR, "Safaricom response: $response\n");
    exit(1);
}

echo "Authentication successful!\n";
echo "Access token received.\n";
echo "Expires in: " . ($data['expires_in'] ?? 'unknown') . " seconds.\n";
