<?php

declare(strict_types=1);

namespace Prometheus\Services;

final class HashChainService
{
    public function calculate(array $payload, ?string $previousHash = null): string
    {
        ksort($payload);

        return hash('sha256', json_encode([
            'payload' => $payload,
            'previous_hash' => $previousHash,
        ], JSON_THROW_ON_ERROR));
    }
}
