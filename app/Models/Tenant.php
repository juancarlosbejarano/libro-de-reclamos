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

    public static function slugExists(string $slug): bool
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }

    public static function create(string $slug, string $name, ?string $idType = null, ?string $idNumber = null): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO tenants (slug, name, id_type, id_number, created_at) VALUES (:slug, :name, :id_type, :id_number, NOW())');
        $stmt->execute([
            'slug' => $slug,
            'name' => $name,
            'id_type' => $idType,
            'id_number' => $idNumber,
        ]);
        return (int)$pdo->lastInsertId();
    }
}

