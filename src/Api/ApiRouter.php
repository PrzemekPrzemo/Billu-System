<?php

declare(strict_types=1);

namespace App\Api;

class ApiRouter
{
    private array $routes = [];

    public function get(string $pattern, string $controller, string $action, bool $requiresAuth = true): void
    {
        $this->addRoute('GET', $pattern, $controller, $action, $requiresAuth);
    }

    public function post(string $pattern, string $controller, string $action, bool $requiresAuth = true): void
    {
        $this->addRoute('POST', $pattern, $controller, $action, $requiresAuth);
    }

    public function put(string $pattern, string $controller, string $action, bool $requiresAuth = true): void
    {
        $this->addRoute('PUT', $pattern, $controller, $action, $requiresAuth);
    }

    public function patch(string $pattern, string $controller, string $action, bool $requiresAuth = true): void
    {
        $this->addRoute('PATCH', $pattern, $controller, $action, $requiresAuth);
    }

    public function delete(string $pattern, string $controller, string $action, bool $requiresAuth = true): void
    {
        $this->addRoute('DELETE', $pattern, $controller, $action, $requiresAuth);
    }

    private function addRoute(string $method, string $pattern, string $controller, string $action, bool $requiresAuth): void
    {
        $this->routes[] = compact('method', 'pattern', 'controller', 'action', 'requiresAuth');
    }

    /**
     * Match URI against registered routes.
     * Returns [controller, action, params, requiresAuth] or null.
     */
    public function match(string $method, string $uri): ?array
    {
        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;

        // Remove /api/v1 prefix
        $path = preg_replace('#^/api/v1#', '', $path);
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPattern($route['pattern'], $path);
            if ($params !== null) {
                return [
                    'controller'   => $route['controller'],
                    'action'       => $route['action'],
                    'params'       => $params,
                    'requiresAuth' => $route['requiresAuth'],
                ];
            }
        }

        return null;
    }

    private function matchPattern(string $pattern, string $path): ?array
    {
        $pattern = rtrim($pattern, '/') ?: '/';

        // Convert {param} placeholders to named capture groups
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        // Extract only named captures (string keys)
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return $params;
    }
}
