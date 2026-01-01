<?php
declare(strict_types=1);

namespace App\Models;

final class PlatformUser
{
    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM platform_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function create(string $email, string $password, string $role): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO platform_users (email, password_hash, role, created_at) VALUES (:email, :hash, :role, NOW())');
        $stmt->execute(['email' => $email, 'hash' => $hash, 'role' => $role]);
        return (int)$pdo->lastInsertId();
    }
}
