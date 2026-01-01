<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Support\Env;

function isCli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function out(string $s): void
{
    if (isCli()) {
        echo $s;
        return;
    }
    echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function outLn(string $s = ''): void
{
    out($s . (isCli() ? "\n" : "\n"));
}

function renderWebHeader(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />'
        . '<link rel="stylesheet" href="/assets/app.css" /><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>'
        . '<body><div class="container">'
        . '<div class="nav"><a href="/platform" class="btn">Platform</a><a href="/platform/jobs" class="btn">Jobs</a><span class="muted" style="margin-left:auto">Plesk</span></div>';
}

function renderWebFooter(): void
{
    echo '</div></body></html>';
}

function requireOwnerIfWeb(): void
{
    if (isCli()) return;
    $platformUser = $_SESSION['platform_user'] ?? null;
    $role = is_array($platformUser) ? (string)($platformUser['role'] ?? '') : '';
    if (!$platformUser) {
        http_response_code(403);
        renderWebHeader('Sin permisos');
        echo '<h1>Sin permisos</h1><p class="error">Debes iniciar sesión en la plataforma.</p>'
            . '<p><a class="btn primary" href="/platform/login">Ir a /platform/login</a></p>';
        renderWebFooter();
        exit;
    }
    if ($role !== 'owner') {
        http_response_code(403);
        renderWebHeader('Sin permisos');
        echo '<h1>Sin permisos</h1><p class="error">Se requiere rol owner para ejecutar esta comprobación.</p>'
            . '<p class="muted">Usuario actual: ' . htmlspecialchars((string)($platformUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ')</p>';
        renderWebFooter();
        exit;
    }
}

requireOwnerIfWeb();

$url = (string)(Env::get('PLESK_API_URL', '') ?? '');
$key = (string)(Env::get('PLESK_API_KEY', '') ?? '');
$verifyTls = Env::bool('PLESK_VERIFY_TLS', true);

if ($url === '' || $key === '') {
    if (!isCli()) {
        http_response_code(500);
        renderWebHeader('Plesk ping');
        echo '<h1>Plesk ping</h1>';
        echo '<p class="error">Falta configurar PLESK_API_URL o PLESK_API_KEY.</p>';
        echo '<div class="card"><p class="muted">Configura estas variables en tu <strong>.env</strong>:</p>'
            . '<pre style="margin:0">PLESK_API_URL="https://127.0.0.1:8443/enterprise/control/agent.php"\nPLESK_API_KEY="TU_API_KEY"\nPLESK_VERIFY_TLS=true</pre>'
            . '<p class="muted" style="margin-top:12px">Nota: si tu Plesk usa certificado self-signed, puedes poner <strong>PLESK_VERIFY_TLS=false</strong>.</p>'
            . '</div>';
        renderWebFooter();
        exit(1);
    }
    outLn("Missing PLESK_API_URL or PLESK_API_KEY");
    outLn("Set them in .env, e.g.");
    outLn("  PLESK_API_URL=\"https://127.0.0.1:8443/enterprise/control/agent.php\"");
    outLn("  PLESK_API_KEY=\"...\"");
    outLn("  PLESK_VERIFY_TLS=true");
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

    if (!isCli()) {
        renderWebHeader('Plesk ping');
        echo '<h1>Plesk ping</h1>';
        echo '<p class="muted">HTTP ' . (int)$status . '</p>';
        echo '<pre style="white-space: pre-wrap">' . htmlspecialchars($body === false ? '' : (string)$body, ENT_QUOTES, 'UTF-8') . '</pre>';
        if ($body === false) {
            echo '<p class="error">cURL error: ' . htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        renderWebFooter();
        exit($body === false ? 1 : 0);
    }

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
