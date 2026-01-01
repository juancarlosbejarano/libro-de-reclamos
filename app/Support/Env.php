<?php
declare(strict_types=1);

namespace App\Support;

final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    private static ?string $loadedPath = null;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Allow shell-style env files.
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            // Handle UTF-8 BOM in the first key.
            $key = ltrim($key, "\xEF\xBB\xBF");
            $value = trim(substr($line, $pos + 1));
            $value = self::stripQuotes($value);
            self::$values[$key] = $value;
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }

        self::$loadedPath = $path;
    }

    public static function loadedPath(): ?string
    {
        return self::$loadedPath;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) return $default;
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function stripQuotes(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }
        return $value;
    }
}
