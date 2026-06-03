<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;

final class UserService
{
    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, surname, email, username, role, active, last_login_at, created_at
             FROM users
             ORDER BY surname, name, username'
        );

        return $statement->fetchAll();
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, surname, email, username, password, role, active, created_at, updated_at)
             VALUES (:name, :surname, :email, :username, :password, :role, 1, NOW(), NOW())'
        );
        $statement->execute([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
