<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Env;
use PDO;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $host = Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
        $port = Env::get('DB_PORT', '3306') ?? '3306';
        $db = Env::get('DB_DATABASE', '') ?? '';
        $user = Env::get('DB_USERNAME', '') ?? '';
        $pass = Env::get('DB_PASSWORD', '') ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$pdo = $pdo;
        return $pdo;
    }
}
