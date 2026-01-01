<?php
declare(strict_types=1);

namespace App\Models;

final class SystemKV
{
    public static function set(string $key, string $value): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO system_kv (k, v, updated_at) VALUES (:k, :v, NOW()) ON DUPLICATE KEY UPDATE v=VALUES(v), updated_at=NOW()');
        $stmt->execute(['k' => $key, 'v' => $value]);
    }

    public static function get(string $key): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT k, v, updated_at FROM system_kv WHERE k = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}
