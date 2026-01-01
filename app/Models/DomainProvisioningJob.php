<?php
declare(strict_types=1);

namespace App\Models;

final class DomainProvisioningJob
{
    public static function enqueueAliasCreate(int $tenantId, string $domain): void
    {
        $pdo = DB::pdo();
        // Ignore duplicates (unique key)
        $stmt = $pdo->prepare(
            'INSERT INTO domain_provisioning_jobs (tenant_id, domain, action, status, attempts, created_at) '
            . 'VALUES (:tid, :domain, :action, :status, 0, NOW()) '
            . 'ON DUPLICATE KEY UPDATE status = IF(status = "success", status, "pending")'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'domain' => $domain,
            'action' => 'alias_create',
            'status' => 'pending',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listPending(int $limit = 10): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM domain_provisioning_jobs WHERE status = "pending" ORDER BY id ASC LIMIT ' . (int)$limit);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function markSuccess(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE domain_provisioning_jobs SET status="success", processed_at=NOW(), last_error=NULL WHERE id=:id');
        $stmt->execute(['id' => $id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE domain_provisioning_jobs SET status="failed", processed_at=NOW(), last_error=:e WHERE id=:id');
        $stmt->execute(['id' => $id, 'e' => $error]);
    }

    public static function incrementAttempts(int $id, ?string $error = null): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE domain_provisioning_jobs SET attempts = attempts + 1, last_error = :e WHERE id = :id');
        $stmt->execute(['id' => $id, 'e' => $error]);
    }
}
