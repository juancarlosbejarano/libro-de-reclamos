<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DB;
use App\Support\Crypto;

final class WhatsAppConfig
{
    /** @return array{enabled:bool,base_url:string,account_id:int,inbox_id:int,token:string}|null */
    public static function forTenant(int $tenantId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT enabled, chatwoot_base_url, account_id, inbox_id, api_token_enc FROM tenant_whatsapp_settings WHERE tenant_id = :tid LIMIT 1');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $enabled = ((int)$row['enabled'] === 1);
        if (!$enabled) {
            return null;
        }
        $token = Crypto::decrypt((string)$row['api_token_enc']);
        return [
            'enabled' => true,
            'base_url' => (string)$row['chatwoot_base_url'],
            'account_id' => (int)$row['account_id'],
            'inbox_id' => (int)$row['inbox_id'],
            'token' => $token,
        ];
    }
}
