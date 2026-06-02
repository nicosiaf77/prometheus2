<?php

declare(strict_types=1);

namespace Prometheus\Services;

final class AuditService
{
    public function record(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): array
    {
        return compact('action', 'entityType', 'entityId', 'description');
    }
}
