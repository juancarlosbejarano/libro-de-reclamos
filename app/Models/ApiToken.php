<?php
declare(strict_types=1);

namespace App\Models;

use App\Http\Request;
use App\Support\Str;

final class ApiToken
{
    public static function issue(int $userId, int $tenantId): string
    {
        $plain = Str::randomHex(32);
        $hash = hash('sha256', $plain);
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO api_tokens (tenant_id, user_id, token_hash, created_at) VALUES (:tenant_id, :user_id, :token_hash, NOW())');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'token_hash' => $hash,
        ]);
        return $plain;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForUser(int $tenantId, int $userId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, created_at FROM api_tokens WHERE tenant_id = :tid AND user_id = :uid ORDER BY id DESC');
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function revokeAllForUser(int $tenantId, int $userId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE tenant_id = :tid AND user_id = :uid');
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId]);
    }

    public static function revokeOne(int $tenantId, int $userId, int $tokenId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE tenant_id = :tid AND user_id = :uid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'uid' => $userId, 'id' => $tokenId]);
    }

    /** @return array{user_id:int,tenant_id:int,role:string}|null */
    public static function authenticateRequest(Request $request): ?array
    {
        $auth = $request->header('authorization') ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return null;
        }
        $plain = trim($m[1]);
        if ($plain === '') return null;
        $hash = hash('sha256', $plain);

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT t.tenant_id, t.user_id, u.role FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = :h LIMIT 1');
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return [
            'tenant_id' => (int)$row['tenant_id'],
            'user_id' => (int)$row['user_id'],
            'role' => (string)$row['role'],
        ];
    }
}
