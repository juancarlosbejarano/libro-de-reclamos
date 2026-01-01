<?php
declare(strict_types=1);

namespace App\Models;

final class TenantDomain
{
    /** @return array<int,array<string,mixed>> */
    public static function listForTenant(int $tenantId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, domain, kind, is_primary, verified_at, created_at FROM tenant_domains WHERE tenant_id = :tid ORDER BY is_primary DESC, id DESC');
        $stmt->execute(['tid' => $tenantId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function addCustom(int $tenantId, string $domain, bool $makePrimary = false, bool $verified = false): void
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            if ($makePrimary) {
                $stmt = $pdo->prepare('UPDATE tenant_domains SET is_primary = 0 WHERE tenant_id = :tid');
                $stmt->execute(['tid' => $tenantId]);
            }
            $stmt = $pdo->prepare('INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at) VALUES (:tid, :domain, :kind, :primary, :verified_at, NOW())');
            $stmt->execute([
                'tid' => $tenantId,
                'domain' => $domain,
                'kind' => 'custom',
                'primary' => $makePrimary ? 1 : 0,
                'verified_at' => $verified ? date('Y-m-d H:i:s') : null,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function domainExists(string $domain, ?int $excludeTenantId = null): bool
    {
        $pdo = DB::pdo();
        if ($excludeTenantId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM tenant_domains WHERE domain = :d AND tenant_id <> :tid LIMIT 1');
            $stmt->execute(['d' => $domain, 'tid' => $excludeTenantId]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM tenant_domains WHERE domain = :d LIMIT 1');
        $stmt->execute(['d' => $domain]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Upserts the tenant subdomain record (kind=subdomain) without changing other domains.
     */
    public static function upsertSubdomain(int $tenantId, string $domain): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, is_primary FROM tenant_domains WHERE tenant_id = :tid AND kind = "subdomain" ORDER BY is_primary DESC, id ASC LIMIT 1');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();

        if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            $id = (int)$row['id'];
            $stmt = $pdo->prepare('UPDATE tenant_domains SET domain = :d WHERE id = :id');
            $stmt->execute(['d' => $domain, 'id' => $id]);
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at) VALUES (:tid, :domain, :kind, 0, NOW(), NOW())');
        $stmt->execute(['tid' => $tenantId, 'domain' => $domain, 'kind' => 'subdomain']);
    }
}

