<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;

final class AuditLogService
{
    public function latest(int $limit = 200): array
    {
        $statement = Database::connection()->prepare(
            'SELECT audit_logs.id,
                    audit_logs.action,
                    audit_logs.entity_type,
                    audit_logs.entity_id,
                    audit_logs.ip_address,
                    audit_logs.description,
                    audit_logs.created_at,
                    users.username
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
