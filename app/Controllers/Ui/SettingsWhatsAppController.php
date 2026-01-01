<?php
declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Services\TenantResolver;
use App\Support\Csrf;
use App\Support\Crypto;
use App\Views\View;

final class SettingsWhatsAppController
{
    public function show(Request $request): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) return Response::redirect('/login');
        if (($user['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        $tenantId = TenantResolver::tenantId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT enabled, chatwoot_base_url, account_id, inbox_id FROM tenant_whatsapp_settings WHERE tenant_id=:tid LIMIT 1');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return Response::html(View::render('settings/whatsapp', [
            'settings' => is_array($row) ? $row : null,
        ]));
    }

    public function save(Request $request): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) return Response::redirect('/login');
        if (($user['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('settings/whatsapp', ['error' => 'CSRF inválido', 'settings' => null]), 400);
        }

        $enabled = isset($request->post['enabled']) && (string)$request->post['enabled'] === '1';
        $baseUrl = trim((string)($request->post['chatwoot_base_url'] ?? ''));
        $accountId = (int)($request->post['account_id'] ?? 0);
        $inboxId = (int)($request->post['inbox_id'] ?? 0);
        $token = (string)($request->post['api_token'] ?? '');

        if ($enabled) {
            if ($baseUrl === '' || $accountId <= 0 || $inboxId <= 0 || $token === '') {
                return Response::html(View::render('settings/whatsapp', ['error' => 'Completa URL/Account/Inbox/Token', 'settings' => null]), 422);
            }
        }

        $tenantId = TenantResolver::tenantId();
        $pdo = DB::pdo();

        // If disabling, keep existing token_enc but mark disabled.
        if (!$enabled) {
            $existing = '';
            try {
                $sel = $pdo->prepare('SELECT api_token_enc FROM tenant_whatsapp_settings WHERE tenant_id=:tid LIMIT 1');
                $sel->execute(['tid' => $tenantId]);
                $row = $sel->fetch();
                if (is_array($row) && isset($row['api_token_enc'])) {
                    $existing = (string)$row['api_token_enc'];
                }
            } catch (\Throwable $e) {
                $existing = '';
            }
            $stmt = $pdo->prepare(
                'INSERT INTO tenant_whatsapp_settings (tenant_id, enabled, chatwoot_base_url, account_id, inbox_id, api_token_enc, created_at, updated_at) '
                . 'VALUES (:tid, 0, :base, :aid, :iid, :tok, NOW(), NOW()) '
                . 'ON DUPLICATE KEY UPDATE enabled=0, updated_at=NOW()'
            );
            $stmt->execute([
                'tid' => $tenantId,
                'base' => $baseUrl !== '' ? $baseUrl : 'https://portalchat.arca.digital',
                'aid' => max(1, $accountId),
                'iid' => max(1, $inboxId),
                'tok' => $existing !== '' ? $existing : 'disabled',
            ]);
            return Response::redirect('/settings/whatsapp');
        }

        try {
            $tokenEnc = Crypto::encrypt($token);
        } catch (\Throwable $e) {
            return Response::html(View::render('settings/whatsapp', ['error' => 'No se pudo cifrar token (revisa APP_KEY)', 'settings' => null]), 500);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tenant_whatsapp_settings (tenant_id, enabled, chatwoot_base_url, account_id, inbox_id, api_token_enc, created_at, updated_at) '
            . 'VALUES (:tid, 1, :base, :aid, :iid, :tok, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE enabled=1, chatwoot_base_url=VALUES(chatwoot_base_url), account_id=VALUES(account_id), inbox_id=VALUES(inbox_id), api_token_enc=VALUES(api_token_enc), updated_at=NOW()'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'base' => $baseUrl,
            'aid' => $accountId,
            'iid' => $inboxId,
            'tok' => $tokenEnc,
        ]);

        return Response::redirect('/settings/whatsapp');
    }
}
