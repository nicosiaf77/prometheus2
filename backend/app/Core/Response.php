<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Response
{
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'application/json; charset=UTF-8'],
    ) {
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_THROW_ON_ERROR), $status);
    }

    public static function redirect(string $path, int $status = 302): self
    {
        return new self('', $status, ['Location' => $path]);
    }
}
