<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\TenantResolver;

final class User
{
    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $tenantId = TenantResolver::tenantId();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE tenant_id = :tenant_id AND email = :email LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'email' => $email]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public static function findByIdForTenant(int $tenantId, int $userId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForTenant(int $tenantId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, email, role, created_at FROM users WHERE tenant_id = :tid ORDER BY id ASC');
        $stmt->execute(['tid' => $tenantId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function create(int $tenantId, string $email, string $password, string $role): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, role, created_at) VALUES (:tid, :email, :hash, :role, NOW())');
        $stmt->execute(['tid' => $tenantId, 'email' => $email, 'hash' => $hash, 'role' => $role]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateRole(int $tenantId, int $userId, string $role): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['role' => $role, 'tid' => $tenantId, 'id' => $userId]);
    }

    public static function updatePassword(int $tenantId, int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['hash' => $hash, 'tid' => $tenantId, 'id' => $userId]);
    }

    public static function countAdmins(int $tenantId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM users WHERE tenant_id = :tid AND role = "admin"');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return is_array($row) ? (int)($row['c'] ?? 0) : 0;
    }

    /** @return list<string> */
    public static function listAdminEmails(int $tenantId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT email FROM users WHERE tenant_id = :tid AND role = "admin"');
        $stmt->execute(['tid' => $tenantId]);
        $rows = $stmt->fetchAll();
        $emails = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (is_array($r) && isset($r['email']) && is_string($r['email'])) {
                    $emails[] = $r['email'];
                }
            }
        }
        $emails = array_values(array_unique(array_filter($emails, fn($v) => is_string($v) && $v !== '')));
        return $emails;
    }
}

