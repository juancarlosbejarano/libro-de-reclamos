<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Env;

$url = (string)(Env::get('PLESK_API_URL', '') ?? '');
$key = (string)(Env::get('PLESK_API_KEY', '') ?? '');
$verifyTls = Env::bool('PLESK_VERIFY_TLS', true);

if ($url === '' || $key === '') {
    echo "Missing PLESK_API_URL or PLESK_API_KEY\n";
    exit(1);
}

// Minimal request: server info
$xml = '<?xml version="1.0" encoding="UTF-8"?>'
    . '<packet version="1.6.9.0">'
    . '<server><get><gen_info/></get></server>'
    . '</packet>';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml',
            'X-API-Key: ' . $key,
            'KEY: ' . $key,
        ],
        CURLOPT_SSL_VERIFYPEER => $verifyTls,
        CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP {$status}\n";
    if ($body === false) {
        echo "cURL error: {$err}\n";
        exit(1);
    }
    echo (string)$body . "\n";
    exit(0);
}

echo "cURL not available. Enable PHP cURL extension for best results.\n";
exit(2);
