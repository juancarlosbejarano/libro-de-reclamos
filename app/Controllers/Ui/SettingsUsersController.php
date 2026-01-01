<?php
declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Http\Request;
use App\Http\Response;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\TenantResolver;
use App\Support\Csrf;
use App\Views\View;

final class SettingsUsersController
{
    public function index(Request $request): Response
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser) return Response::redirect('/login');
        if (($currentUser['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }

        $tenantId = TenantResolver::tenantId();
        $users = User::listForTenant($tenantId);

        // Attach tokens for bot users
        foreach ($users as $i => $u) {
            if (is_array($u) && (string)($u['role'] ?? '') === 'bot') {
                $users[$i]['tokens'] = ApiToken::listForUser($tenantId, (int)($u['id'] ?? 0));
            }
        }

        return Response::html(View::render('settings/users', [
            'users' => $users,
        ]));
    }

    public function handle(Request $request): Response
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser) return Response::redirect('/login');
        if (($currentUser['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('settings/users', ['error' => 'CSRF inválido', 'users' => []]), 400);
        }

        $tenantId = TenantResolver::tenantId();
        $action = (string)($request->post['action'] ?? '');

        try {
            if ($action === 'create') {
                $email = trim((string)($request->post['email'] ?? ''));
                $password = (string)($request->post['password'] ?? '');
                $role = (string)($request->post['role'] ?? 'user');

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Email inválido');
                }
                if (mb_strlen($password) < 8) {
                    throw new \RuntimeException('Password muy corto (mín. 8)');
                }
                if (!in_array($role, ['staff', 'user', 'bot'], true)) {
                    throw new \RuntimeException('Rol inválido');
                }
                User::create($tenantId, $email, $password, $role);
                return Response::redirect('/settings/users');
            }

            if ($action === 'role') {
                $userId = (int)($request->post['user_id'] ?? 0);
                $role = (string)($request->post['role'] ?? '');
                if ($userId <= 0) throw new \RuntimeException('Usuario inválido');
                if (!in_array($role, ['admin', 'staff', 'user', 'bot'], true)) {
                    throw new \RuntimeException('Rol inválido');
                }
                // Prevent removing last admin
                if ($role !== 'admin') {
                    $admins = User::countAdmins($tenantId);
                    $target = User::findByIdForTenant($tenantId, $userId);
                    if ($target && ($target['role'] ?? '') === 'admin' && $admins <= 1) {
                        throw new \RuntimeException('No puedes quitar el último admin');
                    }
                }
                User::updateRole($tenantId, $userId, $role);
                return Response::redirect('/settings/users');
            }

            if ($action === 'password') {
                $userId = (int)($request->post['user_id'] ?? 0);
                $password = (string)($request->post['password'] ?? '');
                if ($userId <= 0) throw new \RuntimeException('Usuario inválido');
                if (mb_strlen($password) < 8) {
                    throw new \RuntimeException('Password muy corto (mín. 8)');
                }
                User::updatePassword($tenantId, $userId, $password);
                return Response::redirect('/settings/users');
            }

            if ($action === 'token_create') {
                $userId = (int)($request->post['user_id'] ?? 0);
                if ($userId <= 0) throw new \RuntimeException('Usuario inválido');
                $target = User::findByIdForTenant($tenantId, $userId);
                if (!$target) throw new \RuntimeException('Usuario inválido');
                if ((string)($target['role'] ?? '') !== 'bot') {
                    throw new \RuntimeException('Solo cuentas bot pueden tener tokens');
                }
                $plain = ApiToken::issue($userId, $tenantId);
                $users = User::listForTenant($tenantId);
                foreach ($users as $i => $u) {
                    if (is_array($u) && (string)($u['role'] ?? '') === 'bot') {
                        $users[$i]['tokens'] = ApiToken::listForUser($tenantId, (int)($u['id'] ?? 0));
                    }
                }
                return Response::html(View::render('settings/users', [
                    'users' => $users,
                    'token_plain' => $plain,
                ]));
            }

            if ($action === 'token_revoke') {
                $userId = (int)($request->post['user_id'] ?? 0);
                if ($userId <= 0) throw new \RuntimeException('Usuario inválido');
                $target = User::findByIdForTenant($tenantId, $userId);
                if (!$target) throw new \RuntimeException('Usuario inválido');
                if ((string)($target['role'] ?? '') !== 'bot') {
                    throw new \RuntimeException('Solo cuentas bot pueden tener tokens');
                }
                ApiToken::revokeAllForUser($tenantId, $userId);
                return Response::redirect('/settings/users');
            }

            if ($action === 'token_revoke_one') {
                $userId = (int)($request->post['user_id'] ?? 0);
                $tokenId = (int)($request->post['token_id'] ?? 0);
                if ($userId <= 0 || $tokenId <= 0) throw new \RuntimeException('Token inválido');
                $target = User::findByIdForTenant($tenantId, $userId);
                if (!$target) throw new \RuntimeException('Usuario inválido');
                if ((string)($target['role'] ?? '') !== 'bot') {
                    throw new \RuntimeException('Solo cuentas bot pueden tener tokens');
                }
                ApiToken::revokeOne($tenantId, $userId, $tokenId);
                return Response::redirect('/settings/users');
            }

            throw new \RuntimeException('Acción inválida');
        } catch (\Throwable $e) {
            $users = User::listForTenant($tenantId);
            foreach ($users as $i => $u) {
                if (is_array($u) && (string)($u['role'] ?? '') === 'bot') {
                    $users[$i]['tokens'] = ApiToken::listForUser($tenantId, (int)($u['id'] ?? 0));
                }
            }
            return Response::html(View::render('settings/users', [
                'users' => $users,
                'error' => $e->getMessage(),
            ]), 422);
        }
    }
}
