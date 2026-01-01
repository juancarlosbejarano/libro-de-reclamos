<?php
declare(strict_types=1);

namespace App\Support;

final class Str
{
    public static function randomHex(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
