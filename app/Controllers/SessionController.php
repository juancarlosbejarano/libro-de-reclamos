<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Support\Csrf;
use App\Views\View;

final class SessionController
{
    public function showLogin(Request $request): Response
    {
        return Response::html(View::render('auth/login'));
    }

    public function login(Request $request): Response
    {
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('auth/login', ['error' => 'CSRF inválido']), 400);
        }

        $email = trim((string)($request->post['email'] ?? ''));
        $password = (string)($request->post['password'] ?? '');
        if ($email === '' || $password === '') {
            return Response::html(View::render('auth/login', ['error' => 'Credenciales inválidas']), 422);
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return Response::html(View::render('auth/login', ['error' => 'Credenciales inválidas']), 401);
        }

        // Bot accounts are intended for API usage, not UI sessions.
        if (((string)($user['role'] ?? '')) === 'bot') {
            return Response::html(View::render('auth/login', ['error' => 'Cuenta bot: usa API token (no UI)']), 403);
        }

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];

        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/');
        }
        unset($_SESSION['user']);
        return Response::redirect('/');
    }
}
