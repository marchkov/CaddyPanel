<?php

namespace CaddyPanel\Core;

class Router
{
    private array $routes = [];

    public function __construct(private Request $request)
    {
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(): void
    {
        $method = $this->request->method();
        $path = $this->request->path();

        $routes = $this->routes[$method] ?? [];

        if ($method === 'HEAD' && $routes === []) {
            $routes = $this->routes['GET'] ?? [];
        }

        foreach ($routes as $route) {
            $params = [];

            if ($this->matches($route['path'], $path, $params)) {
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        Response::notFound();
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $normalized = '/' . trim($path, '/');
        $this->routes[$method][] = [
            'path' => $normalized === '/' ? '/' : rtrim($normalized, '/'),
            'handler' => $handler,
        ];
    }

    private function matches(string $pattern, string $path, array &$params): bool
    {
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return false;
        }

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[] = $value;
            }
        }

        return true;
    }
}
