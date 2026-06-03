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
        $params = [];

        if ($handler === null) {
            foreach ($this->routes[$method] ?? [] as $routePath => $routeHandler) {
                $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $routePath);

                if (!is_string($pattern)) {
                    continue;
                }

                if (preg_match('#^' . $pattern . '$#', $path, $matches) === 1) {
                    $handler = $routeHandler;
                    $params = array_slice($matches, 1);
                    break;
                }
            }
        }

        if ($handler === null) {
            return Response::json(['ok' => false, 'error' => 'Endpoint non trovato.'], 404);
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();

        return $controller->{$action}(...$params);
    }
}
