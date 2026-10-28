<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** Who may open a route. */
    public const SETUP = 'setup'; // only while no admin exists
    public const GUEST = 'guest'; // admin exists, not logged in
    public const AUTH  = 'auth';  // logged-in admin

    /** @var list<array{method: string, regex: string, handler: callable, access: string}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, string $access): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'access');
    }

    /** @return array{handler: callable, params: array<string, string>, access: string}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['regex'], $path, $found)) {
                continue;
            }

            return [
                'handler' => $route['handler'],
                'params'  => array_filter($found, 'is_string', ARRAY_FILTER_USE_KEY),
                'access'  => $route['access'],
            ];
        }

        return null;
    }
}
