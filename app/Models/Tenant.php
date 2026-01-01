<?php
declare(strict_types=1);

namespace App\Models;

final class Tenant
{
    /** @return array<string,mixed>|null */
    public static function findByHost(string $host): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT t.* FROM tenant_domains d JOIN tenants t ON t.id = d.tenant_id WHERE d.domain = :domain LIMIT 1');
        $stmt->execute(['domain' => $host]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function slugExists(string $slug): bool
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }

    public static function create(string $slug, string $name, ?string $idType = null, ?string $idNumber = null, ?string $addressFull = null): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO tenants (slug, name, id_type, id_number, address_full, created_at) VALUES (:slug, :name, :id_type, :id_number, :address_full, NOW())');
        $stmt->execute([
            'slug' => $slug,
            'name' => $name,
            'id_type' => $idType,
            'id_number' => $idNumber,
            'address_full' => $addressFull,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateDetails(int $id, string $name, ?string $addressFull, ?string $logoPath): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE tenants SET name = :name, address_full = :address_full, logo_path = :logo_path WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'address_full' => $addressFull,
            'logo_path' => $logoPath,
        ]);
    }

    public static function suspend(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE tenants SET status = "suspended", suspended_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function reactivate(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE tenants SET status = "active", suspended_at = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

