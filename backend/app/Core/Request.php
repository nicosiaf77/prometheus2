<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Request
{
    private ?array $jsonPayload = null;

    public function input(string $key, ?string $default = null): ?string
    {
        $payload = $this->json();
        $value = $_POST[$key] ?? $_GET[$key] ?? $payload[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function array(string $key): array
    {
        $payload = $this->json();
        $value = $_POST[$key] ?? $_GET[$key] ?? $payload[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_scalar($item) && trim((string) $item) !== ''));
    }

    public function json(): array
    {
        if ($this->jsonPayload !== null) {
            return $this->jsonPayload;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!str_contains(strtolower($contentType), 'application/json')) {
            $this->jsonPayload = [];

            return $this->jsonPayload;
        }

        $rawBody = file_get_contents('php://input');
        $decoded = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : [];
        $this->jsonPayload = is_array($decoded) ? $decoded : [];

        return $this->jsonPayload;
    }

    public function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}
