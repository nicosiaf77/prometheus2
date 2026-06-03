<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use PDO;

final class AuditLogService
{
    public function paginate(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $page = max(1, $page);
        $perPage = min(200, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $countStatement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM audit_logs ' . $whereSql
        );
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

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
             ' . $whereSql . '
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_STR);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'data' => $statement->fetchAll(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['action'] ?? '') !== '') {
            $where[] = 'audit_logs.action = :action';
            $params['action'] = $filters['action'];
        }

        if (($filters['entity_type'] ?? '') !== '') {
            $where[] = 'audit_logs.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $where[] = 'DATE(audit_logs.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $where[] = 'DATE(audit_logs.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        return [
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
        ];
    }
}
