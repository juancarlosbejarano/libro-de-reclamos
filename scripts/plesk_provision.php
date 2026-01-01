<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Models\DomainProvisioningJob;
use App\Models\SystemKV;
use App\Support\Env;
use App\Services\PleskClient;

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
    out($s . "\n");
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
        echo '<h1>Sin permisos</h1><p class="error">Se requiere rol owner para ejecutar esta tarea.</p>'
            . '<p class="muted">Usuario actual: ' . htmlspecialchars((string)($platformUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ')</p>';
        renderWebFooter();
        exit;
    }
}

requireOwnerIfWeb();

$auto = Env::bool('PLESK_AUTO_PROVISION', true);
if (!$auto) {
    if (!isCli()) {
        http_response_code(200);
        renderWebHeader('Plesk provision');
        echo '<h1>Plesk provision</h1><p class="muted">PLESK_AUTO_PROVISION está desactivado.</p>';
        renderWebFooter();
        exit(0);
    }
    outLn('PLESK_AUTO_PROVISION disabled');
    exit(0);
}

$site = (string)(Env::get('PLESK_SITE_NAME', '') ?? '');
if ($site === '') {
    if (isCli()) {
        $site = (string)($argv[1] ?? '');
    } else {
        $site = (string)($_GET['site'] ?? '');
    }
}

$loadedEnv = Env::loadedPath();
if ($site === '') {
    if (!isCli()) {
        http_response_code(500);
        renderWebHeader('Plesk provision');
        echo '<h1>Plesk provision</h1>';
        echo '<p class="error">Falta configurar PLESK_SITE_NAME.</p>';
        echo '<p class="muted">.env cargado desde: <strong>' . htmlspecialchars($loadedEnv ?: '(no encontrado)', ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<div class="card"><p class="muted">Opciones:</p>'
            . '<ol style="margin:0 0 0 18px">'
            . '<li>Define <strong>PLESK_SITE_NAME</strong> en tu <strong>.env</strong> (recomendado).</li>'
            . '<li>Para ejecución manual en web: <code>/scripts/plesk_provision.php?site=tu-dominio-principal</code></li>'
            . '<li>Para ejecución manual en CLI: <code>php scripts/plesk_provision.php tu-dominio-principal</code></li>'
            . '</ol></div>';
        renderWebFooter();
        exit(1);
    }
    outLn('Missing PLESK_SITE_NAME');
    outLn('.env loaded from: ' . ($loadedEnv ?: '(not found)'));
    outLn('Provide it via .env, or pass as argument: php scripts/plesk_provision.php <site>');
    exit(1);
}

$jobs = DomainProvisioningJob::listPending(25);
if (!$jobs) {
    if (!isCli()) {
        http_response_code(200);
        renderWebHeader('Plesk provision');
        echo '<h1>Plesk provision</h1>';
        echo '<p class="muted">Site: <strong>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<p class="muted">No hay jobs pendientes.</p>';
        renderWebFooter();
        try {
            SystemKV::set('plesk_provision_last_run', 'no_pending');
        } catch (Throwable $e) {
        }
        exit(0);
    }
    outLn('No pending jobs');
    try {
        SystemKV::set('plesk_provision_last_run', 'no_pending');
    } catch (Throwable $e) {
    }
    exit(0);
}

try {
    SystemKV::set('plesk_provision_last_run', 'started');
} catch (Throwable $e) {
}

foreach ($jobs as $job) {
    $id = (int)$job['id'];
    $domain = (string)$job['domain'];
    DomainProvisioningJob::incrementAttempts($id);

    $res = PleskClient::createDomainAlias($site, $domain);
    if ($res['ok']) {
        DomainProvisioningJob::markSuccess($id);
        outLn("OK alias created: {$domain}");
        continue;
    }
    $err = ($res['error'] ?? 'unknown') . ' (http ' . (string)$res['status'] . ')';
    DomainProvisioningJob::markFailed($id, $err);
    outLn("FAIL {$domain}: {$err}");

    // Helpful diagnostics for manual runs (owner-only on web).
    $body = (string)($res['body'] ?? '');
    $body = trim($body);
    if ($body !== '') {
        $bodyOneLine = preg_replace('~\s+~', ' ', $body);
        $bodyOneLine = trim((string)$bodyOneLine);
        if (strlen($bodyOneLine) > 700) {
            $bodyOneLine = substr($bodyOneLine, 0, 700) . '…';
        }
        outLn('Response snippet: ' . $bodyOneLine);
    }
}

try {
    SystemKV::set('plesk_provision_last_run', 'finished');
} catch (Throwable $e) {
}
