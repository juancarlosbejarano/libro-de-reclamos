<?php
declare(strict_types=1);

namespace App\Models;

final class ComplaintResponse
{
    /** @return array<int,array<string,mixed>> */
    public static function listForComplaint(int $tenantId, int $complaintId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare(
            'SELECT r.id, r.message, r.created_at, r.email_sent_at, r.whatsapp_sent_at, r.email_error, r.whatsapp_error, u.email AS user_email '
            . 'FROM complaint_responses r JOIN users u ON u.id = r.user_id '
            . 'WHERE r.tenant_id = :tid AND r.complaint_id = :cid '
            . 'ORDER BY r.id ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $complaintId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public static function create(int $tenantId, int $complaintId, int $userId, string $message): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO complaint_responses (tenant_id, complaint_id, user_id, message, created_at) VALUES (:tid, :cid, :uid, :msg, NOW())');
        $stmt->execute(['tid' => $tenantId, 'cid' => $complaintId, 'uid' => $userId, 'msg' => $message]);
        return (int)$pdo->lastInsertId();
    }

    public static function markEmailSent(int $tenantId, int $responseId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE complaint_responses SET email_sent_at = NOW(), email_error = NULL WHERE tenant_id=:tid AND id=:id');
        $stmt->execute(['tid' => $tenantId, 'id' => $responseId]);
    }

    public static function markEmailFailed(int $tenantId, int $responseId, string $error): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE complaint_responses SET email_error = :e WHERE tenant_id=:tid AND id=:id');
        $stmt->execute(['tid' => $tenantId, 'id' => $responseId, 'e' => mb_substr($error, 0, 255)]);
    }

    public static function markWhatsAppSent(int $tenantId, int $responseId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE complaint_responses SET whatsapp_sent_at = NOW(), whatsapp_error = NULL WHERE tenant_id=:tid AND id=:id');
        $stmt->execute(['tid' => $tenantId, 'id' => $responseId]);
    }

    public static function markWhatsAppFailed(int $tenantId, int $responseId, string $error): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE complaint_responses SET whatsapp_error = :e WHERE tenant_id=:tid AND id=:id');
        $stmt->execute(['tid' => $tenantId, 'id' => $responseId, 'e' => mb_substr($error, 0, 255)]);
    }
}
