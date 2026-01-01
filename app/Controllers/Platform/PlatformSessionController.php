<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\PlatformUser;
use App\Support\Csrf;
use App\Views\View;

final class PlatformSessionController
{
    public function showLogin(Request $request): Response
    {
        return Response::html(View::render('platform/login'));
    }

    public function login(Request $request): Response
    {
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('platform/login', ['error' => 'CSRF inválido']), 400);
        }

        $email = trim((string)($request->post['email'] ?? ''));
        $password = (string)($request->post['password'] ?? '');
        if ($email === '' || $password === '') {
            return Response::html(View::render('platform/login', ['error' => 'Credenciales inválidas']), 422);
        }

        $user = PlatformUser::findByEmail($email);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            return Response::html(View::render('platform/login', ['error' => 'Credenciales inválidas']), 401);
        }

        $_SESSION['platform_user'] = [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];

        return Response::redirect('/platform');
    }

    public function logout(Request $request): Response
    {
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/platform');
        }
        unset($_SESSION['platform_user']);
        return Response::redirect('/platform/login');
    }
}
