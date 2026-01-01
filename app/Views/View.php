<?php
declare(strict_types=1);

namespace App\Views;

use App\Services\I18n;
use App\Support\Csrf;

final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $base = __DIR__ . '/templates';
        $file = $base . '/' . $template . '.php';
        if (!is_file($file)) {
            return '<h1>Template not found</h1>';
        }

        $t = fn(string $key, array $params = []) => I18n::t($key, $params);
        $csrf = fn() => Csrf::token();
        $currentUser = $_SESSION['user'] ?? null;

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}
