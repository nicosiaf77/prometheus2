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

    public function findByName(string $name): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM events WHERE name = :name LIMIT 1');
        $statement->execute(['name' => mb_strtoupper(trim($name), 'UTF-8')]);
        $event = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($event) ? $event : null;
    }
}
