<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Application
{
    public function __construct(private readonly string $basePath)
    {
        Env::load($this->basePath . '/.env');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'OPTIONS') {
            Session::start();
        }
    }

    public function run(Router $router): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $corsHeaders = $this->corsHeaders();
        $response = $method === 'OPTIONS'
            ? Response::json(['ok' => true])
            : $router->dispatch($method, $_SERVER['REQUEST_URI'] ?? '/');

        http_response_code($response->status);

        foreach ($corsHeaders + $response->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->body;
    }

    private function corsHeaders(): array
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $defaultOrigins = Env::get('FRONTEND_URL', 'http://localhost:5173') . ',http://localhost:3000,http://localhost:8081';
        $allowedOrigins = array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', Env::get('CORS_ALLOWED_ORIGINS', $defaultOrigins) ?? $defaultOrigins)
        ));

        if ($origin === '' || $allowedOrigins === []) {
            return [];
        }

        $wildcardAllowed = in_array('*', $allowedOrigins, true);

        if (!$wildcardAllowed && !in_array($origin, $allowedOrigins, true)) {
            return [];
        }

        $allowCredentials = (Env::get('CORS_ALLOW_CREDENTIALS', 'true') ?? 'true') === 'true';
        $allowOrigin = $wildcardAllowed && !$allowCredentials ? '*' : $origin;

        $headers = [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-Requested-With',
            'Vary' => 'Origin',
        ];

        if ($allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }
}
