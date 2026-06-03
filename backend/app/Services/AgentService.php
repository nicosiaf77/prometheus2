<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;

final class AgentService
{
    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, surname, rank, office, active, created_at, updated_at
             FROM agents
             ORDER BY surname, name'
        );

        return $statement->fetchAll();
    }

    public function create(array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO agents (name, surname, rank, office, active, created_at, updated_at)
             VALUES (:name, :surname, :rank, :office, 1, NOW(), NOW())'
        );
        $statement->execute([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'rank' => $data['rank'] !== '' ? $data['rank'] : null,
            'office' => $data['office'] !== '' ? $data['office'] : null,
        ]);
    }
}
