<?php
declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = Str::randomHex(16);
        }
        return (string)$_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? null;
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }
}
