<?php
declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Services\TenantResolver;
use App\Support\Csrf;
use App\Support\Crypto;
use App\Support\Env;
use App\Views\View;

final class SettingsMailController
{
    public function mail(Request $request): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return Response::redirect('/login');
        }
        if (($user['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }

        $tenantId = TenantResolver::tenantId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT driver, host, port, username, encryption, from_email, from_name FROM tenant_mail_settings WHERE tenant_id = :tid LIMIT 1');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();

        $defaults = [
            'driver' => (string)(Env::get('MAIL_DRIVER', 'smtp') ?? 'smtp'),
            'host' => (string)(Env::get('MAIL_HOST', 'smtp.office365.com') ?? 'smtp.office365.com'),
            'port' => (int)(Env::get('MAIL_PORT', '587') ?? '587'),
            'username' => (string)(Env::get('MAIL_USERNAME', '') ?? ''),
            'encryption' => (string)(Env::get('MAIL_ENCRYPTION', 'tls') ?? 'tls'),
            'from_email' => (string)(Env::get('MAIL_FROM_EMAIL', '') ?? ''),
            'from_name' => (string)(Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'App') ?? 'App') ?? 'App'),
        ];

        return Response::html(View::render('settings/mail', [
            'settings' => is_array($row) ? $row : null,
            'defaults' => $defaults,
        ]));
    }

    public function saveMail(Request $request): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return Response::redirect('/login');
        }
        if (($user['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('settings/mail', ['error' => 'CSRF inválido', 'settings' => null, 'defaults' => []]), 400);
        }

        $host = trim((string)($request->post['host'] ?? ''));
        $port = (int)($request->post['port'] ?? 0);
        $username = trim((string)($request->post['username'] ?? ''));
        $password = (string)($request->post['password'] ?? '');
        $encryption = trim((string)($request->post['encryption'] ?? 'tls'));
        $fromEmail = trim((string)($request->post['from_email'] ?? ''));
        $fromName = trim((string)($request->post['from_name'] ?? ''));

        if ($host === '' || $port <= 0 || $username === '' || $fromEmail === '') {
            return Response::html(View::render('settings/mail', ['error' => 'Completa host/puerto/usuario/from', 'settings' => null, 'defaults' => []]), 422);
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            return Response::html(View::render('settings/mail', ['error' => 'Encryption inválido', 'settings' => null, 'defaults' => []]), 422);
        }
        if ($password === '') {
            return Response::html(View::render('settings/mail', ['error' => 'Password requerido', 'settings' => null, 'defaults' => []]), 422);
        }

        try {
            $passwordEnc = Crypto::encrypt($password);
        } catch (\Throwable $e) {
            return Response::html(View::render('settings/mail', ['error' => 'No se pudo cifrar password (revisa APP_KEY)', 'settings' => null, 'defaults' => []]), 500);
        }

        $tenantId = TenantResolver::tenantId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO tenant_mail_settings (tenant_id, driver, host, port, username, password_enc, encryption, from_email, from_name, created_at, updated_at) '
            . 'VALUES (:tid, :driver, :host, :port, :username, :pass, :enc, :from_email, :from_name, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE driver=VALUES(driver), host=VALUES(host), port=VALUES(port), username=VALUES(username), password_enc=VALUES(password_enc), encryption=VALUES(encryption), from_email=VALUES(from_email), from_name=VALUES(from_name), updated_at=NOW()'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'driver' => 'smtp',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'pass' => $passwordEnc,
            'enc' => $encryption,
            'from_email' => $fromEmail,
            'from_name' => $fromName !== '' ? $fromName : (string)(Env::get('APP_NAME', 'App') ?? 'App'),
        ]);

        return Response::redirect('/settings/mail');
    }
}
