<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Models\DB;
use App\Support\Csrf;

header('Content-Type: text/html; charset=utf-8');

$platformUser = $_SESSION['platform_user'] ?? null;
$role = is_array($platformUser) ? (string)($platformUser['role'] ?? '') : '';

if (!$platformUser) {
    http_response_code(403);
  echo '<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />'
    . '<link rel="stylesheet" href="/assets/app.css" /><title>Sin permisos</title></head><body><div class="container">'
    . '<h1>Sin permisos</h1>'
    . '<p class="error">Debes iniciar sesión en la plataforma para ejecutar actualizaciones.</p>'
    . '<p><a class="btn primary" href="/platform/login">Ir a /platform/login</a></p>'
    . '</div></body></html>';
    exit;
}

if ($role !== 'owner') {
    http_response_code(403);
  echo '<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />'
    . '<link rel="stylesheet" href="/assets/app.css" /><title>Sin permisos</title></head><body><div class="container">'
    . '<h1>Sin permisos</h1>'
    . '<p class="error">No tienes permisos para ejecutar update.php. Se requiere el rol owner.</p>'
    . '<p class="muted">Usuario actual: ' . htmlspecialchars((string)($platformUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ')</p>'
    . '<p><a class="btn" href="/platform">Volver a /platform</a></p>'
    . '</div></body></html>';
    exit;
}

$pdo = DB::pdo();

/** @return bool */
function columnExists(\PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
    $stmt->execute(['t' => $table, 'c' => $column]);
    return ((int)$stmt->fetchColumn()) > 0;
}

/** @return bool */
function tableExists(\PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
    $stmt->execute(['t' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

$migrations = [];

// Migration: ensure system_kv exists (older installs)
$migrations[] = [
    'id' => '2026_01_01_system_kv',
    'label' => 'Ensure system_kv table exists',
    'needed' => !tableExists($pdo, 'system_kv'),
    'run' => function () use ($pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_kv (k VARCHAR(64) NOT NULL, v TEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    },
];

// Migration: tenants.id_type + tenants.id_number for RUC/DNI
$migrations[] = [
    'id' => '2026_01_01_tenants_id_fields',
    'label' => 'Add tenants.id_type and tenants.id_number',
    'needed' => !(columnExists($pdo, 'tenants', 'id_type') && columnExists($pdo, 'tenants', 'id_number')),
    'run' => function () use ($pdo): void {
        // Add columns one-by-one to be safe/idempotent.
        if (!columnExists($pdo, 'tenants', 'id_type')) {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN id_type ENUM('ruc','dni') NULL");
        }
        if (!columnExists($pdo, 'tenants', 'id_number')) {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN id_number VARCHAR(16) NULL");
        }
    },
];

// Migration: tenants.address_full for RUC address storage
$migrations[] = [
  'id' => '2026_01_01_tenants_address_full',
  'label' => 'Add tenants.address_full',
  'needed' => !columnExists($pdo, 'tenants', 'address_full'),
  'run' => function () use ($pdo): void {
    if (!columnExists($pdo, 'tenants', 'address_full')) {
      $pdo->exec("ALTER TABLE tenants ADD COLUMN address_full VARCHAR(255) NULL");
    }
  },
];

// Migration: tenants.status/suspended_at/logo_path for platform administration
$migrations[] = [
  'id' => '2026_01_01_tenants_admin_fields',
  'label' => 'Add tenants.status, tenants.suspended_at, tenants.logo_path',
  'needed' => !(columnExists($pdo, 'tenants', 'status') && columnExists($pdo, 'tenants', 'suspended_at') && columnExists($pdo, 'tenants', 'logo_path')),
  'run' => function () use ($pdo): void {
    if (!columnExists($pdo, 'tenants', 'status')) {
      $pdo->exec("ALTER TABLE tenants ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active'");
    }
    if (!columnExists($pdo, 'tenants', 'suspended_at')) {
      $pdo->exec("ALTER TABLE tenants ADD COLUMN suspended_at DATETIME NULL");
    }
    if (!columnExists($pdo, 'tenants', 'logo_path')) {
      $pdo->exec("ALTER TABLE tenants ADD COLUMN logo_path VARCHAR(255) NULL");
    }
  },
];

$needsAny = false;
foreach ($migrations as $m) {
    if (!empty($m['needed'])) { $needsAny = true; break; }
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $error = 'Invalid CSRF token';
    } else {
        try {
            foreach ($migrations as $m) {
                if (!empty($m['needed'])) {
                    ($m['run'])();
                }
            }
            $message = 'Update completed.';
            // Recompute needed flags after running
            $migrations[0]['needed'] = !tableExists($pdo, 'system_kv');
            $migrations[1]['needed'] = !(columnExists($pdo, 'tenants', 'id_type') && columnExists($pdo, 'tenants', 'id_number'));
            $migrations[2]['needed'] = !columnExists($pdo, 'tenants', 'address_full');
            $migrations[3]['needed'] = !(columnExists($pdo, 'tenants', 'status') && columnExists($pdo, 'tenants', 'suspended_at') && columnExists($pdo, 'tenants', 'logo_path'));
            $needsAny = ($migrations[0]['needed'] || $migrations[1]['needed'] || $migrations[2]['needed'] || $migrations[3]['needed']);
        } catch (Throwable $e) {
            http_response_code(500);
            $error = 'Update failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/assets/app.css" />
  <title>Update</title>
</head>
<body>
  <div class="container">
    <div class="nav">
      <a href="/platform" class="btn">Platform</a>
      <a href="/platform/tenants" class="btn">Tenants</a>
      <span class="muted" style="margin-left:auto">
        <?= htmlspecialchars((string)($platformUser['email'] ?? '')) ?>
      </span>
    </div>

    <h1>Update</h1>

    <?php if ($message): ?>
      <p class="muted"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <div class="card">
      <p class="muted">This page can only be executed by the platform owner.</p>
      <table class="table">
        <thead>
          <tr>
            <th>Migration</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($migrations as $m): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars((string)$m['id']) ?></strong><br />
                <span class="muted"><?= htmlspecialchars((string)$m['label']) ?></span>
              </td>
              <td><?= !empty($m['needed']) ? 'pending' : 'ok' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post" style="margin-top:12px">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>" />
        <button class="btn primary" type="submit" <?= $needsAny ? '' : 'disabled' ?>>Run update</button>
      </form>

      <p class="muted" style="margin-top:12px">Security: after running, delete httpdocs/update.php.</p>
    </div>
  </div>
</body>
</html>
