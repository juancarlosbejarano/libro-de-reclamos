<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DB;
use App\Support\Crypto;
use App\Support\Env;

final class MailConfig
{
    /** @return array{driver:string,host:string,port:int,username:string,password:string,encryption:string,from_email:string,from_name:string} */
    public static function forTenant(int $tenantId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM tenant_mail_settings WHERE tenant_id = :tid LIMIT 1');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            $password = Crypto::decrypt((string)$row['password_enc']);
            return [
                'driver' => (string)$row['driver'],
                'host' => (string)$row['host'],
                'port' => (int)$row['port'],
                'username' => (string)$row['username'],
                'password' => $password,
                'encryption' => (string)$row['encryption'],
                'from_email' => (string)$row['from_email'],
                'from_name' => (string)$row['from_name'],
            ];
        }

        // Fallback to env defaults
        return [
            'driver' => (string)(Env::get('MAIL_DRIVER', 'smtp') ?? 'smtp'),
            'host' => (string)(Env::get('MAIL_HOST', '') ?? ''),
            'port' => (int)(Env::get('MAIL_PORT', '587') ?? '587'),
            'username' => (string)(Env::get('MAIL_USERNAME', '') ?? ''),
            'password' => (string)(Env::get('MAIL_PASSWORD', '') ?? ''),
            'encryption' => (string)(Env::get('MAIL_ENCRYPTION', 'tls') ?? 'tls'),
            'from_email' => (string)(Env::get('MAIL_FROM_EMAIL', '') ?? ''),
            'from_name' => (string)(Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'App') ?? 'App') ?? 'App'),
        ];
    }
}
