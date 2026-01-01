<?php
declare(strict_types=1);

// Basic autoloader for App\\* classes.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Support\Env;

// Load .env from common locations (Plesk deployments may place it in different folders).
$envCandidates = [
    __DIR__ . '/../.env',
    __DIR__ . '/../httpdocs/.env',
];
if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') {
    $envCandidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/.env';
}
$cwd = getcwd();
if (is_string($cwd) && $cwd !== '') {
    $envCandidates[] = rtrim($cwd, '/\\') . '/.env';
}

foreach ($envCandidates as $p) {
    if (is_string($p) && $p !== '' && is_file($p)) {
        Env::load($p);
        break;
    }
}

// Sessions (for UI auth)
$sessionName = Env::get('SESSION_NAME', 'LIBROSESSID');
$sessionSecure = Env::bool('SESSION_SECURE', true);

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Error handling
$debug = Env::bool('APP_DEBUG', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) use ($debug): void {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    if ($debug) {
        echo "Unhandled exception\n\n";
        echo $e;
        return;
    }
    echo "Server error";
});
