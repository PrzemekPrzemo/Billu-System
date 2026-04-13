<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            $this->callHandler($this->routes[$method][$uri]);
            return;
        }

        // Try pattern matching
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callHandler($handler, $params);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/../../templates/errors/404.php';
    }

    private function callHandler(array $handler, array $params = []): void
    {
        try {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass();
            call_user_func_array([$controller, $method], array_values($params));
        } catch (\Throwable $e) {
            error_log("Uncaught exception in {$handler[0]}::{$handler[1]}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            if (!headers_sent()) {
                http_response_code(500);
            }
            $errorMessage = $e->getMessage();
            $errorFile = $e->getFile() . ':' . $e->getLine();
            require __DIR__ . '/../../templates/errors/500.php';
        }
    }
}
