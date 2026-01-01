<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        /** @var array<string,string> */
        public readonly array $headers,
        /** @var array<string,mixed> */
        public readonly array $query,
        /** @var array<string,mixed> */
        public readonly array $post,
        /** @var array<string,mixed> */
        public readonly array $files,
        public readonly string $rawBody,
        public readonly string $host,
    ) {}

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $headers[$name] = (string)$v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string)$_SERVER['CONTENT_TYPE'];
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) $raw = '';

        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(trim(explode(':', $host)[0]));

        return new self(
            $method,
            $path,
            $headers,
            $_GET,
            $_POST,
            $_FILES,
            $raw,
            $host,
        );
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }

    /** @return array<string,mixed>|null */
    public function json(): ?array
    {
        $contentType = $this->header('content-type') ?? '';
        if (!str_contains(strtolower($contentType), 'application/json')) {
            return null;
        }
        $data = json_decode($this->rawBody, true);
        return is_array($data) ? $data : null;
    }
}
