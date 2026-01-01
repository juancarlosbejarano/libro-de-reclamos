<?php
declare(strict_types=1);

// Safe SMTP test runner.
// Guardrails:
// - CLI only
// - Requires MAIL_TEST_ENABLED=true
// - Requires explicit confirmation flag
// - Sends only to tenant admin emails (no arbitrary recipients)
// Usage:
//   php scripts/mail_test.php --tenant-slug=miempresa --confirm=true

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

use App\Models\DB;
use App\Models\User;
use App\Services\MailConfig;
use App\Services\SmtpMailer;
use App\Support\Env;

/** @return array<string,string> */
function parseArgs(array $argv): array {
    $out = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$k, $v] = explode('=', substr($arg, 2), 2);
            $out[$k] = $v;
        }
    }
    return $out;
}

$args = parseArgs($argv);
$tenantSlug = trim((string)($args['tenant-slug'] ?? ''));
$confirm = strtolower(trim((string)($args['confirm'] ?? 'false')));

if (!Env::bool('MAIL_TEST_ENABLED', false)) {
    fwrite(STDERR, "MAIL_TEST_ENABLED is false. Enable it temporarily in .env to run this test.\n");
    exit(2);
}

if ($confirm !== 'true') {
    fwrite(STDERR, "Refusing to send email. Re-run with --confirm=true\n");
    exit(3);
}

if ($tenantSlug === '') {
    fwrite(STDERR, "Missing --tenant-slug=...\n");
    exit(4);
}

// Simple rate-limit: one send per minute per tenant slug
$lockDir = __DIR__ . '/../storage/logs';
@mkdir($lockDir, 0775, true);
$lockFile = $lockDir . '/mail_test_' . preg_replace('/[^a-z0-9_-]+/i', '_', $tenantSlug) . '.lock';
$now = time();
if (is_file($lockFile)) {
    $last = (int)@file_get_contents($lockFile);
    if ($last > 0 && ($now - $last) < 60) {
        fwrite(STDERR, "Rate limit: wait before sending another test (60s).\n");
        exit(5);
    }
}
@file_put_contents($lockFile, (string)$now);

$pdo = DB::pdo();
$stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
$stmt->execute(['slug' => $tenantSlug]);
$tenantId = (int)$stmt->fetchColumn();
if ($tenantId <= 0) {
    fwrite(STDERR, "Tenant not found: {$tenantSlug}\n");
    exit(6);
}

$to = User::listAdminEmails($tenantId);
if (!$to) {
    fwrite(STDERR, "No admin emails found for tenant\n");
    exit(7);
}

$cfg = MailConfig::forTenant($tenantId);
$subject = "SMTP test (tenant {$tenantSlug})";
$body = "This is a test email from Libro de Reclamaciones.\n" .
    "Tenant: {$tenantSlug}\n" .
    "Time: " . date('c') . "\n";

try {
    SmtpMailer::send($cfg, $to, $subject, $body);
    echo "OK sent to: " . implode(', ', $to) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(10);
}
