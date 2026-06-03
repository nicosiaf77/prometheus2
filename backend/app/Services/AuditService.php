<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\Request;

final class AuditService
{
    public function record(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): array
    {
        $request = new Request();
        $payload = compact('action', 'entityType', 'entityId', 'description');
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, description, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :description, NOW())'
        );

        $statement->execute([
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => $description,
        ]);

        return $payload;
    }
}
