<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Application
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function run(Router $router): void
    {
        $response = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->body;
    }
}
