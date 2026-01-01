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
}

