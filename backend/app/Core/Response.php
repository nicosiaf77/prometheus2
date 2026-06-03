<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Response
{
    public string $body;
    public int $status;
    public array $headers;

    public function __construct(
        string $body,
        int $status = 200,
        array $headers = ['Content-Type' => 'application/json; charset=UTF-8']
    ) {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
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
