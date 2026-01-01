<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\TenantResolver;

final class ApiAuthController
{
    public function token(Request $request): Response
    {
        $json = $request->json();
        if (!$json) {
            return Response::json(['error' => 'invalid_json'], 400);
        }
        $email = trim((string)($json['email'] ?? ''));
        $password = (string)($json['password'] ?? '');
        if ($email === '' || $password === '') {
            return Response::json(['error' => 'invalid_credentials'], 422);
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return Response::json(['error' => 'invalid_credentials'], 401);
        }

        // Enforce tenant match
        $tenantId = TenantResolver::tenantId();
        if ((int)$user['tenant_id'] !== $tenantId) {
            return Response::json(['error' => 'tenant_mismatch'], 403);
        }

        $token = ApiToken::issue((int)$user['id'], $tenantId);
        return Response::json(['token' => $token, 'token_type' => 'Bearer']);
    }
}
