<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;

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
}
