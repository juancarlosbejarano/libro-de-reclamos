<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Env;

final class Complaint
{
    /** @return array<int,array<string,mixed>> */
    public static function listForTenant(int $tenantId, int $limit): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, subject, status, created_at FROM complaints WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT ' . (int)$limit);
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForExternalUser(int $tenantId, int $userId, string $email, int $limit): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, subject, status, created_at '
            . 'FROM complaints '
            . 'WHERE tenant_id = :tenant_id AND (created_by_user_id = :uid OR customer_email = :email) '
            . 'ORDER BY id DESC LIMIT ' . (int)$limit
        );
        $stmt->execute(['tenant_id' => $tenantId, 'uid' => $userId, 'email' => $email]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function belongsToExternalUser(array $complaint, int $userId, string $email): bool
    {
        $cuid = (int)($complaint['created_by_user_id'] ?? 0);
        $cemail = strtolower(trim((string)($complaint['customer_email'] ?? '')));
        $email = strtolower(trim($email));
        return ($cuid > 0 && $cuid === $userId) || ($email !== '' && $cemail !== '' && $cemail === $email);
    }

    /** @return array<string,mixed>|null */
    public static function findForTenant(int $tenantId, int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM complaints WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed>|null $attachment
     */
    public static function createForTenant(
        int $tenantId,
        string $subject,
        string $message,
        ?int $createdByUserId,
        ?array $attachment,
        ?string $customerName = null,
        ?string $customerEmail = null,
        ?string $customerPhone = null
    ): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO complaints (tenant_id, created_by_user_id, customer_name, customer_email, customer_phone, subject, message, status, created_at) VALUES (:tenant_id, :uid, :cn, :ce, :cp, :subject, :message, :status, NOW())');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'uid' => $createdByUserId,
            'cn' => $customerName,
            'ce' => $customerEmail,
            'cp' => $customerPhone,
            'subject' => $subject,
            'message' => $message,
            'status' => 'open',
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($attachment && isset($attachment['tmp_name']) && is_uploaded_file((string)$attachment['tmp_name'])) {
            self::saveAttachment($tenantId, $id, $attachment);
        }

        return $id;
    }

    public static function setChatwootConversationId(int $tenantId, int $complaintId, int $conversationId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE complaints SET chatwoot_conversation_id = :cid WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['cid' => $conversationId, 'tid' => $tenantId, 'id' => $complaintId]);
    }

    /** @param array<string,mixed> $attachment */
    private static function saveAttachment(int $tenantId, int $complaintId, array $attachment): void
    {
        $uploadsDir = Env::get('UPLOADS_DIR', 'storage/uploads') ?? 'storage/uploads';
        $base = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
        $targetDir = $base . '/' . trim($uploadsDir, '/\\') . '/' . $tenantId;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $original = (string)($attachment['name'] ?? 'file');
        $original = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $original) ?: 'file';
        $filename = $complaintId . '_' . time() . '_' . $original;
        $dest = $targetDir . '/' . $filename;
        @move_uploaded_file((string)$attachment['tmp_name'], $dest);

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO complaint_attachments (tenant_id, complaint_id, original_name, stored_path, created_at) VALUES (:tenant_id, :cid, :on, :sp, NOW())');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'cid' => $complaintId,
            'on' => $original,
            'sp' => $dest,
        ]);
    }
}
