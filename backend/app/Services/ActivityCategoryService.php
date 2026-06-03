<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use PDO;

final class ActivityCategoryService
{
    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, description, active, created_at, updated_at
             FROM activity_categories
             ORDER BY name'
        );

        return $statement->fetchAll();
    }

    public function active(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name
             FROM activity_categories
             WHERE active = 1
             ORDER BY name'
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, description, active, created_at, updated_at
             FROM activity_categories
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $cat = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($cat) ? $cat : null;
    }

    public function create(string $name, ?string $description): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO activity_categories (name, description, active, created_at, updated_at)
             VALUES (:name, :description, 1, NOW(), NOW())'
        );
        $statement->execute([
            'name'        => trim($name),
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        Database::connection()->prepare(
            'UPDATE activity_categories
             SET name = :name,
                 description = :description,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'name'        => trim($name),
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'id'          => $id,
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        Database::connection()->prepare(
            'UPDATE activity_categories SET active = :active, updated_at = NOW() WHERE id = :id'
        )->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function isNameTaken(string $name, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM activity_categories WHERE name = :name';
        $params = ['name' => trim($name)];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
