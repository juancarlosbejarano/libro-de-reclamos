<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:callable}>> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $method = strtoupper($method);
        $this->routes[$method][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(Request $request): ?Response
    {
        $routes = $this->routes[$request->method] ?? [];
        foreach ($routes as $route) {
            $matches = [];
            if (preg_match($route['pattern'], $request->path, $matches) === 1) {
                return ($route['handler'])($request, $matches);
            }
        }
        return null;
    }
}
