<?php
declare(strict_types=1);

namespace App\Services;

final class UpdateChecker
{
    public static function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
        $stmt->execute(['t' => $table]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public static function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
        $stmt->execute(['t' => $table, 'c' => $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public static function needsUpdate(\PDO $pdo): bool
    {
        // Keep these checks in sync with httpdocs/update.php.
        if (!self::tableExists($pdo, 'system_kv')) return true;

        $needsIdFields = !(self::columnExists($pdo, 'tenants', 'id_type') && self::columnExists($pdo, 'tenants', 'id_number'));
        if ($needsIdFields) return true;

        if (!self::columnExists($pdo, 'tenants', 'address_full')) return true;

        $needsAdminFields = !(self::columnExists($pdo, 'tenants', 'status')
            && self::columnExists($pdo, 'tenants', 'suspended_at')
            && self::columnExists($pdo, 'tenants', 'logo_path'));
        if ($needsAdminFields) return true;

        return false;
    }
}
