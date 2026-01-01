<?php
declare(strict_types=1);

// CLI-only script to create platform owner/support users.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "Not found";
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\Models\PlatformUser;

$args = $argv;
array_shift($args);

$email = null;
$password = null;
$role = 'support';

foreach ($args as $a) {
    if (str_starts_with($a, '--email=')) $email = substr($a, 8);
    if (str_starts_with($a, '--password=')) $password = substr($a, 11);
    if (str_starts_with($a, '--role=')) $role = substr($a, 7);
}

if (!$email || !$password) {
    echo "Usage: php scripts/create_platform_user.php --email=owner@arca.digital --password=StrongPass123 --role=owner\n";
    exit(2);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email\n";
    exit(2);
}

if (!in_array($role, ['owner', 'support'], true)) {
    echo "Invalid role (owner|support)\n";
    exit(2);
}

if (mb_strlen($password) < 10) {
    echo "Password too short (min 10)\n";
    exit(2);
}

try {
    PlatformUser::create($email, $password, $role);
    echo "OK platform user created: {$email} ({$role})\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
