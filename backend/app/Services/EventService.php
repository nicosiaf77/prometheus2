<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use PDO;

final class EventService
{
    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT events.id, events.name, users.username AS created_by_username, events.created_at, events.updated_at
             FROM events
             LEFT JOIN users ON users.id = events.created_by
             ORDER BY events.name'
        );

        return $statement->fetchAll();
    }

    public function create(string $name, int $createdBy): void
    {
        $normalizedName = mb_strtoupper(trim($name), 'UTF-8');

        $statement = Database::connection()->prepare(
            'INSERT INTO events (name, created_by, created_at, updated_at)
             VALUES (:name, :created_by, NOW(), NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()'
        );
        $statement->execute([
            'name' => $normalizedName,
            'created_by' => $createdBy,
        ]);
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT events.id, events.name, users.username AS created_by_username, events.created_at, events.updated_at
             FROM events
             LEFT JOIN users ON users.id = events.created_by
             WHERE events.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $event = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($event) ? $event : null;
    }

    public function update(int $id, string $name): void
    {
        $normalizedName = mb_strtoupper(trim($name), 'UTF-8');

        Database::connection()->prepare(
            'UPDATE events SET name = :name, updated_at = NOW() WHERE id = :id'
        )->execute(['name' => $normalizedName, 'id' => $id]);
    }

    public function isNameTaken(string $name, ?int $excludeId = null): bool
    {
        $normalized = mb_strtoupper(trim($name), 'UTF-8');
        $sql        = 'SELECT COUNT(*) FROM events WHERE name = :name';
        $params     = ['name' => $normalized];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findByName(string $name): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM events WHERE name = :name LIMIT 1');
        $statement->execute(['name' => mb_strtoupper(trim($name), 'UTF-8')]);
        $event = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($event) ? $event : null;
    }
}
