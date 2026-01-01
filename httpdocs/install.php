<?php
declare(strict_types=1);

// Web installer for "Libro de Reclamaciones" (Plesk-friendly, no Composer).
// After successful install, this creates storage/install.lock. Delete this file after installing.

$rootDir = dirname(__DIR__);

// Storage can live either in project root (recommended) or inside httpdocs (common in some Plesk setups).
// If httpdocs/storage exists, we use it.
$storageDir = $rootDir . '/storage';
$httpdocsStorage = __DIR__ . '/storage';
if (is_dir($httpdocsStorage)) {
    $storageDir = $httpdocsStorage;
}

$lockFile = rtrim($storageDir, '/\\') . '/install.lock';
$envFile = $rootDir . '/.env';
$schemaFile = $rootDir . '/database/schema.sql';

// --- helpers ---------------------------------------------------------------

/** @return array{0:string,1:string} */
function hpair(string $label, string $value): array { return [$label, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function isHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

function currentHost(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $host = strtolower(trim(explode(':', $host)[0]));
    return $host;
}

function randomAppKey(): string
{
    return 'base64:' . base64_encode(random_bytes(32));
}

function envQuote(string $value): string
{
    // Always quote to avoid parsing surprises (#, spaces, etc.).
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace('"', '\\"', $value);
    return '"' . $value . '"';
}

/**
 * Splits a SQL file into individual statements.
 * Handles strings, backticks, -- comments, # comments, and /* block comments *\/.
 * @return string[]
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buf = '';

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : "\0";

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buf .= $ch;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        // Start of comments (only if not inside a string/backtick)
        if (!$inSingle && !$inDouble && !$inBacktick) {
            // -- comment (MySQL requires space/newline after --)
            if ($ch === '-' && $next === '-') {
                $after = ($i + 2 < $len) ? $sql[$i + 2] : "\0";
                if ($after === ' ' || $after === "\t" || $after === "\r" || $after === "\n" || $after === "\0") {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }
            // # comment
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            // /* block comment */
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        // Toggle string/backtick states
        if (!$inDouble && !$inBacktick && $ch === "'") {
            // handle escaped quotes '' and \'
            $prev = ($i > 0) ? $sql[$i - 1] : "\0";
            if (!$inSingle) {
                $inSingle = true;
            } else {
                if ($prev !== '\\') {
                    // if it's doubled quote, stay in string
                    if ($next === "'") {
                        $buf .= $ch;
                        $buf .= $next;
                        $i++;
                        continue;
                    }
                    $inSingle = false;
                }
            }
        } elseif (!$inSingle && !$inBacktick && $ch === '"') {
            $prev = ($i > 0) ? $sql[$i - 1] : "\0";
            if (!$inDouble) {
                $inDouble = true;
            } else {
                if ($prev !== '\\') {
                    $inDouble = false;
                }
            }
        } elseif (!$inSingle && !$inDouble && $ch === '`') {
            $inBacktick = !$inBacktick;
        }

        // End statement
        if (!$inSingle && !$inDouble && !$inBacktick && $ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    $tail = trim($buf);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

/** @return array<string,string> */
function postData(): array
{
    $out = [];
    foreach ($_POST as $k => $v) {
        if (!is_string($k)) continue;
        if (is_string($v)) $out[$k] = $v;
    }
    return $out;
}

function boolPost(string $key, bool $default = false): bool
{
    if (!isset($_POST[$key])) return $default;
    $v = $_POST[$key];
    if ($v === '1' || $v === 1 || $v === true || $v === 'on' || $v === 'yes' || $v === 'true') return true;
    return false;
}

function render(string $title, string $bodyHtml): void
{
    $css = '/assets/app.css';
    echo "<!doctype html>\n";
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
    echo '<link rel="stylesheet" href="' . h($css) . '" />';
    echo '<title>' . h($title) . '</title>';
    echo '</head><body>';
    echo '<div class="container">';
    echo $bodyHtml;
    echo '</div>';
    echo '</body></html>';
}

function ensureDir(string $path): void
{
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    // Best-effort: on some hosts chmod may be restricted.
    @chmod($path, 0775);
    if (!is_writable($path)) {
        // Last resort (some Plesk setups require group/world write)
        @chmod($path, 0777);
    }
}

/** @return array<string,bool> */
function computeWritableChecks(string $rootDir, string $envFile, string $storageDir): array
{
    ensureDir($storageDir);
    ensureDir(rtrim($storageDir, '/\\') . '/uploads');

    return [
        $envFile => (is_file($envFile) ? is_writable($envFile) : is_writable($rootDir)),
        $storageDir => (is_dir($storageDir) && is_writable($storageDir)),
        rtrim($storageDir, '/\\') . '/uploads' => (is_dir(rtrim($storageDir, '/\\') . '/uploads') && is_writable(rtrim($storageDir, '/\\') . '/uploads')),
    ];
}

// --- lock check ------------------------------------------------------------

if (is_file($lockFile)) {
    $msg = '<h1>Instalador bloqueado</h1>';
    $msg .= '<div class="card">';
    $msg .= '<p>Este sistema ya fue instalado.</p>';
    $msg .= '<p class="muted">Si necesitas reinstalar, elimina <code>storage/install.lock</code> y vuelve a cargar esta página.</p>';
    $msg .= '<p><a class="btn primary" href="/">Ir al sistema</a></p>';
    $msg .= '</div>';
    render('Install (locked)', $msg);
    exit;
}

// --- requirements ----------------------------------------------------------

$requirements = [
    'PHP >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'ext-pdo' => extension_loaded('pdo'),
    'PDO MySQL (pdo_mysql)' => extension_loaded('pdo_mysql'),
    'ext-openssl' => extension_loaded('openssl'),
    'ext-mbstring' => extension_loaded('mbstring'),
    'ext-curl (recomendado para Plesk/Chatwoot)' => extension_loaded('curl'),
];

$writableChecks = computeWritableChecks($rootDir, $envFile, $storageDir);

// --- UI -------------------------------------------------------------------

$hostDefault = currentHost();
$appNameDefault = 'Libro de Reclamaciones';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $reqHtml = '<h1>Instalación</h1>';
    $reqHtml .= '<div class="card">';
    $reqHtml .= '<p>Este instalador crea el archivo <code>.env</code>, ejecuta el esquema SQL y crea el primer usuario de plataforma.</p>';
    $reqHtml .= '<p class="muted">Recomendación: usa HTTPS (Plesk suele proveerlo). Detectado: ' . (isHttps() ? 'HTTPS' : 'HTTP') . '</p>';

    $reqHtml .= '<h3>Requisitos</h3><ul>';
    foreach ($requirements as $label => $ok) {
        $reqHtml .= '<li>' . h($label) . ': ' . ($ok ? 'OK' : 'FALTA') . '</li>';
    }
    $reqHtml .= '</ul>';

    $reqHtml .= '<h3>Permisos</h3><ul>';
    foreach ($writableChecks as $path => $ok) {
        $reqHtml .= '<li>' . h(str_replace('\\', '/', $path)) . ': ' . ($ok ? 'OK' : 'NO ESCRIBIBLE') . '</li>';
    }
    $reqHtml .= '</ul>';
    if (in_array(false, array_values($writableChecks), true)) {
        $reqHtml .= '<p class="muted">Si ves NO ESCRIBIBLE, en Plesk ajusta permisos/propietario del directorio <code>storage</code> y <code>storage/uploads</code> (p.ej. 775) o usa la opción de Plesk para “Fix Permissions”.</p>';
    }
    $reqHtml .= '<p class="muted">Storage detectado en: <code>' . h(str_replace('\\', '/', $storageDir)) . '</code></p>';
    $reqHtml .= '</div>';

    $reqHtml .= '<form method="post" class="card" style="margin-top:12px">';

    $reqHtml .= '<h3>Base de datos (MariaDB/MySQL)</h3>';
    $reqHtml .= '<label>DB_HOST</label><input name="DB_HOST" value="127.0.0.1" required />';
    $reqHtml .= '<label>DB_PORT</label><input name="DB_PORT" value="3306" required />';
    $reqHtml .= '<label>DB_DATABASE</label><input name="DB_DATABASE" value="" required />';
    $reqHtml .= '<label>DB_USERNAME</label><input name="DB_USERNAME" value="" required />';
    $reqHtml .= '<label>DB_PASSWORD</label><input name="DB_PASSWORD" type="password" value="" />';

    $reqHtml .= '<h3 style="margin-top:16px">Aplicación</h3>';
    $reqHtml .= '<label>APP_NAME</label><input name="APP_NAME" value="' . h($appNameDefault) . '" required />';
    $reqHtml .= '<label>DEFAULT_LOCALE</label><input name="DEFAULT_LOCALE" value="es" required />';
    $reqHtml .= '<label>PLATFORM_BASE_DOMAIN</label><input name="PLATFORM_BASE_DOMAIN" value="' . h($hostDefault) . '" required />';
    $reqHtml .= '<label>PLATFORM_ALLOWED_IPS (opcional, para verificación DNS)</label><input name="PLATFORM_ALLOWED_IPS" value="" placeholder="1.2.3.4, 5.6.7.8" />';

    $reqHtml .= '<div style="margin-top:10px">';
    $reqHtml .= '<label><input type="checkbox" name="SESSION_SECURE" value="1" ' . (isHttps() ? 'checked' : '') . ' /> SESSION_SECURE (recomendado con HTTPS)</label><br />';
    $reqHtml .= '<label><input type="checkbox" name="DOMAIN_VERIFY_REQUIRED" value="1" checked /> DOMAIN_VERIFY_REQUIRED</label><br />';
    $reqHtml .= '<label><input type="checkbox" name="ALLOW_SUBDOMAIN_TENANTS" value="1" checked /> ALLOW_SUBDOMAIN_TENANTS</label><br />';
    $reqHtml .= '<label><input type="checkbox" name="PLESK_AUTO_PROVISION" value="1" checked /> PLESK_AUTO_PROVISION (opcional)</label><br />';
    $reqHtml .= '</div>';

    $reqHtml .= '<h3 style="margin-top:16px">Plesk API (opcional)</h3>';
    $reqHtml .= '<label>PLESK_API_URL</label><input name="PLESK_API_URL" value="" placeholder="https://127.0.0.1:8443/enterprise/control/agent.php" />';
    $reqHtml .= '<label>PLESK_API_KEY</label><input name="PLESK_API_KEY" type="password" value="" />';
    $reqHtml .= '<label><input type="checkbox" name="PLESK_VERIFY_TLS" value="1" checked /> PLESK_VERIFY_TLS</label>';

    $reqHtml .= '<h3 style="margin-top:16px">Usuario de plataforma (owner)</h3>';
    $reqHtml .= '<label>Email</label><input name="PLATFORM_OWNER_EMAIL" type="email" value="" required />';
    $reqHtml .= '<label>Password (mín. 10)</label><input name="PLATFORM_OWNER_PASSWORD" type="password" value="" required />';

    $reqHtml .= '<h3 style="margin-top:16px">Admin del tenant plataforma (opcional)</h3>';
    $reqHtml .= '<label><input type="checkbox" name="CREATE_PLATFORM_TENANT_ADMIN" value="1" checked /> Crear admin en tenant "platform"</label>';
    $reqHtml .= '<label>Email admin</label><input name="TENANT_ADMIN_EMAIL" type="email" value="" />';
    $reqHtml .= '<label>Password admin (mín. 10)</label><input name="TENANT_ADMIN_PASSWORD" type="password" value="" />';

    $reqHtml .= '<div style="margin-top:16px">';
    $reqHtml .= '<button class="btn primary" type="submit">Instalar</button>';
    $reqHtml .= '</div>';
    $reqHtml .= '</form>';

    render('Install', $reqHtml);
    exit;
}

// --- POST: perform install -------------------------------------------------

$data = postData();
$errors = [];

// Re-check permissions (and attempt to fix) on POST as well.
$writableChecks = computeWritableChecks($rootDir, $envFile, $storageDir);

foreach ($requirements as $label => $ok) {
    if (!$ok) $errors[] = 'Falta requisito: ' . $label;
}
foreach ($writableChecks as $path => $ok) {
    if (!$ok) $errors[] = 'No es escribible: ' . str_replace('\\', '/', $path);
}

$dbHost = trim($data['DB_HOST'] ?? '');
$dbPort = trim($data['DB_PORT'] ?? '3306');
$dbName = trim($data['DB_DATABASE'] ?? '');
$dbUser = trim($data['DB_USERNAME'] ?? '');
$dbPass = (string)($data['DB_PASSWORD'] ?? '');

$appName = trim($data['APP_NAME'] ?? 'Libro de Reclamaciones');
$defaultLocale = trim($data['DEFAULT_LOCALE'] ?? 'es');
$platformBaseDomain = strtolower(trim($data['PLATFORM_BASE_DOMAIN'] ?? $hostDefault));
$platformAllowedIps = trim($data['PLATFORM_ALLOWED_IPS'] ?? '');

$sessionSecure = boolPost('SESSION_SECURE', isHttps());
$domainVerifyRequired = boolPost('DOMAIN_VERIFY_REQUIRED', true);
$allowSubdomainTenants = boolPost('ALLOW_SUBDOMAIN_TENANTS', true);
$pleskAutoProvision = boolPost('PLESK_AUTO_PROVISION', true);

$pleskApiUrl = trim($data['PLESK_API_URL'] ?? '');
$pleskApiKey = trim($data['PLESK_API_KEY'] ?? '');
$pleskVerifyTls = boolPost('PLESK_VERIFY_TLS', true);

$ownerEmail = strtolower(trim($data['PLATFORM_OWNER_EMAIL'] ?? ''));
$ownerPass = (string)($data['PLATFORM_OWNER_PASSWORD'] ?? '');

$createTenantAdmin = boolPost('CREATE_PLATFORM_TENANT_ADMIN', true);
$tenantAdminEmail = strtolower(trim($data['TENANT_ADMIN_EMAIL'] ?? ''));
$tenantAdminPass = (string)($data['TENANT_ADMIN_PASSWORD'] ?? '');

if ($dbHost === '' || $dbName === '' || $dbUser === '') $errors[] = 'Completa credenciales de BD.';
if (!ctype_digit($dbPort)) $errors[] = 'DB_PORT inválido.';
if ($platformBaseDomain === '' || str_contains($platformBaseDomain, '/')) $errors[] = 'PLATFORM_BASE_DOMAIN inválido.';
if (!in_array($defaultLocale, ['es', 'en'], true)) $errors[] = 'DEFAULT_LOCALE debe ser es o en.';

if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email owner inválido.';
if (mb_strlen($ownerPass) < 10) $errors[] = 'Password owner muy corto (min 10).';

if ($createTenantAdmin) {
    if ($tenantAdminEmail === '' || $tenantAdminPass === '') {
        $errors[] = 'Si creas admin del tenant plataforma, completa TENANT_ADMIN_EMAIL y TENANT_ADMIN_PASSWORD.';
    } else {
        if (!filter_var($tenantAdminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email admin tenant inválido.';
        if (mb_strlen($tenantAdminPass) < 10) $errors[] = 'Password admin tenant muy corto (min 10).';
    }
}

if (!is_file($schemaFile)) {
    $errors[] = 'No se encontró database/schema.sql.';
}

if ($errors) {
    $html = '<h1>Instalación - errores</h1><div class="card"><ul>';
    foreach ($errors as $e) $html .= '<li class="error">' . h($e) . '</li>';
    $html .= '</ul><p><a class="btn" href="/install.php">Volver</a></p></div>';
    render('Install - errors', $html);
    exit;
}

$appKey = randomAppKey();

try {
    $dsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 1) Apply schema
    $sql = file_get_contents($schemaFile);
    if ($sql === false) throw new RuntimeException('No se pudo leer schema.sql');
    $stmts = splitSqlStatements($sql);
    foreach ($stmts as $stmt) {
        $pdo->exec($stmt);
    }

    // 2) Seed platform tenant + domain
    $platformTenantName = $appName . ' (Plataforma)';
    $pdo->prepare("INSERT INTO tenants (slug, name, created_at) VALUES ('platform', :name, NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name)")
        ->execute(['name' => $platformTenantName]);

    $tenantId = (int)$pdo->query("SELECT id FROM tenants WHERE slug='platform' LIMIT 1")->fetchColumn();
    if ($tenantId <= 0) throw new RuntimeException('No se pudo resolver tenant platform');

    $pdo->prepare("INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at)
                   VALUES (:tid, :domain, 'platform', 1, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE kind='platform', is_primary=1, verified_at=NOW()")
        ->execute(['tid' => $tenantId, 'domain' => $platformBaseDomain]);

    // 3) Create/update platform owner user
    $ownerHash = password_hash($ownerPass, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO platform_users (email, password_hash, role, created_at)
                   VALUES (:email, :hash, 'owner', NOW())
                   ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='owner'")
        ->execute(['email' => $ownerEmail, 'hash' => $ownerHash]);

    // 4) Optional: create tenant admin for platform tenant
    if ($createTenantAdmin) {
        $adminHash = password_hash($tenantAdminPass, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, role, created_at)
                   VALUES (:tid, :email, :hash, 'admin', NOW())
                   ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin'")
            ->execute(['tid' => $tenantId, 'email' => $tenantAdminEmail, 'hash' => $adminHash]);
    }

    // 5) Write .env (overwrites if exists but writable)
    $lines = [];
    $lines[] = '# Generated by httpdocs/install.php';
    $lines[] = 'APP_NAME=' . envQuote($appName);
    $lines[] = 'APP_KEY=' . envQuote($appKey);
    $lines[] = 'APP_DEBUG="0"';
    $lines[] = 'DEFAULT_LOCALE=' . envQuote($defaultLocale);
    $lines[] = 'SESSION_NAME="LIBROSESSID"';
    $lines[] = 'SESSION_SECURE="' . ($sessionSecure ? '1' : '0') . '"';
    $lines[] = '';
    $lines[] = 'DB_HOST=' . envQuote($dbHost);
    $lines[] = 'DB_PORT=' . envQuote($dbPort);
    $lines[] = 'DB_DATABASE=' . envQuote($dbName);
    $lines[] = 'DB_USERNAME=' . envQuote($dbUser);
    $lines[] = 'DB_PASSWORD=' . envQuote($dbPass);
    $lines[] = '';
    $lines[] = 'PLATFORM_BASE_DOMAIN=' . envQuote($platformBaseDomain);
    $lines[] = 'PLATFORM_ALLOWED_IPS=' . envQuote($platformAllowedIps);
    $lines[] = 'DEFAULT_TENANT_SLUG="platform"';
    $lines[] = 'ALLOW_SUBDOMAIN_TENANTS="' . ($allowSubdomainTenants ? '1' : '0') . '"';
    $lines[] = 'DOMAIN_VERIFY_REQUIRED="' . ($domainVerifyRequired ? '1' : '0') . '"';
    $lines[] = '';
    $lines[] = 'PLESK_AUTO_PROVISION="' . ($pleskAutoProvision ? '1' : '0') . '"';
    $lines[] = 'PLESK_API_URL=' . envQuote($pleskApiUrl);
    $lines[] = 'PLESK_API_KEY=' . envQuote($pleskApiKey);
    $lines[] = 'PLESK_VERIFY_TLS="' . ($pleskVerifyTls ? '1' : '0') . '"';
    $lines[] = '';
    // If storage lives under httpdocs, uploads must be referenced from project root.
    $uploadsDir = 'storage/uploads';
    if (realpath($storageDir) && realpath($storageDir) === realpath(__DIR__ . '/storage')) {
        $uploadsDir = 'httpdocs/storage/uploads';
    }
    $lines[] = 'UPLOADS_DIR=' . envQuote($uploadsDir);

    $envContent = implode("\n", $lines) . "\n";
    if (file_put_contents($envFile, $envContent) === false) {
        throw new RuntimeException('No se pudo escribir .env');
    }

    // 6) Create lock
    $lockPayload = json_encode([
        'installed_at' => gmdate('c'),
        'host' => $platformBaseDomain,
        'php' => PHP_VERSION,
    ], JSON_UNESCAPED_SLASHES);
    if ($lockPayload === false) $lockPayload = "installed";

    if (file_put_contents($lockFile, $lockPayload . "\n") === false) {
        throw new RuntimeException('No se pudo escribir storage/install.lock');
    }

    $ok = '<h1>Instalación completada</h1>';
    $ok .= '<div class="card">';
    $ok .= '<p><strong>Listo.</strong> Ya puedes usar el sistema.</p>';
    $ok .= '<ul>';
    $ok .= '<li>Platform admin: <a href="/platform/login">/platform/login</a></li>';
    $ok .= '<li>Tenant plataforma: <a href="/">/</a></li>';
    $ok .= '</ul>';
    $ok .= '<p class="muted">Seguridad: elimina <code>install.php</code> (este archivo) del servidor.</p>';
    $ok .= '</div>';

    render('Install - OK', $ok);
    exit;

} catch (Throwable $e) {
    $html = '<h1>Instalación - falló</h1>';
    $html .= '<div class="card">';
    $html .= '<p class="error">' . h($e->getMessage()) . '</p>';
    $html .= '<p class="muted">Tip: revisa credenciales de BD, permisos de escritura y que el usuario tenga permisos para crear tablas.</p>';
    $html .= '<p><a class="btn" href="/install.php">Volver</a></p>';
    $html .= '</div>';
    render('Install - FAIL', $html);
    exit;
}
