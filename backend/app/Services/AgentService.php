<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use PDO;

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

    public function active(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, surname, rank, office
             FROM agents
             WHERE active = 1
             ORDER BY surname, name'
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, surname, rank, office, active, created_at, updated_at
             FROM agents
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $agent = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($agent) ? $agent : null;
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO agents (name, surname, rank, office, active, created_at, updated_at)
             VALUES (:name, :surname, :rank, :office, 1, NOW(), NOW())'
        );
        $statement->execute([
            'name'    => $data['name'],
            'surname' => $data['surname'],
            'rank'    => ($data['rank'] ?? '') !== '' ? $data['rank'] : null,
            'office'  => ($data['office'] ?? '') !== '' ? $data['office'] : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        Database::connection()->prepare(
            'UPDATE agents
             SET name = :name,
                 surname = :surname,
                 rank = :rank,
                 office = :office,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'name'    => $data['name'],
            'surname' => $data['surname'],
            'rank'    => ($data['rank'] ?? '') !== '' ? $data['rank'] : null,
            'office'  => ($data['office'] ?? '') !== '' ? $data['office'] : null,
            'id'      => $id,
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        Database::connection()->prepare(
            'UPDATE agents SET active = :active, updated_at = NOW() WHERE id = :id'
        )->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }
}
