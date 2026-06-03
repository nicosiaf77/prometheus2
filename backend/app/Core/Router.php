<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Router
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

    public function dispatch(string $method, string $uri): Response
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            return new Response('<h1>404</h1><p>Pagina non trovata.</p>', 404);
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();

        return $controller->{$action}();
    }
}
