<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Models\DB;
use App\Support\Env;

// Usage examples:
// php scripts/seed.php --platform-domain=ldr.arca.digital --admin-email=admin@example.com --admin-password=admin12345

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
$platformDomain = $args['platform-domain'] ?? (Env::get('PLATFORM_BASE_DOMAIN', 'ldr.arca.digital') ?? 'ldr.arca.digital');
$adminEmail = $args['admin-email'] ?? 'admin@example.com';
$adminPassword = $args['admin-password'] ?? 'admin12345';

$pdo = DB::pdo();

// Create platform tenant
$pdo->exec("INSERT INTO tenants (slug, name, created_at) VALUES ('platform','Plataforma Libro de Reclamaciones', NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name)");

$stmt = $pdo->prepare("INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at)
  SELECT id, :domain, 'platform', 1, NOW(), NOW() FROM tenants WHERE slug='platform'
  ON DUPLICATE KEY UPDATE kind='platform', is_primary=1");
$stmt->execute(['domain' => $platformDomain]);

// Create admin user for platform tenant
$stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug='platform' LIMIT 1");
$stmt->execute();
$platformTenantId = (int)$stmt->fetchColumn();

$hash = password_hash($adminPassword, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, role, created_at)
  VALUES (:tid, :email, :hash, 'admin', NOW())
  ON DUPLICATE KEY UPDATE role='admin', password_hash=VALUES(password_hash)");
$stmt->execute(['tid' => $platformTenantId, 'email' => $adminEmail, 'hash' => $hash]);

echo "Seed OK\n";
echo "Platform domain: {$platformDomain}\n";
echo "Admin: {$adminEmail}\n";
