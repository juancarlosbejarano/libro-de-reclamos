<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Support\Env;

final class I18n
{
    /** @var array<string,string> */
    private static array $dict = [];
    private static string $locale = 'es';

    public static function bootstrap(Request $request): void
    {
        $default = Env::get('DEFAULT_LOCALE', 'es') ?? 'es';
        $locale = (string)($_SESSION['_locale'] ?? $default);
        $q = $request->query['lang'] ?? null;
        if (is_string($q) && in_array($q, ['es', 'en'], true)) {
            $locale = $q;
        }
        self::$locale = $locale;
        $_SESSION['_locale'] = $locale;

        $path = __DIR__ . '/../../lang/' . $locale . '.php';
        self::$dict = is_file($path) ? (array)require $path : [];
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @param array<string,string|int|float> $params */
    public static function t(string $key, array $params = []): string
    {
        $text = self::$dict[$key] ?? $key;
        foreach ($params as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, $text);
        }
        return $text;
    }
}
