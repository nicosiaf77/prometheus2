<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use PDO;

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

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, surname, email, username, role, active, last_login_at, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, surname, email, username, password, role, active, created_at, updated_at)
             VALUES (:name, :surname, :email, :username, :password, :role, 1, NOW(), NOW())'
        );
        $statement->execute([
            'name'     => $data['name'],
            'surname'  => $data['surname'],
            'email'    => $data['email'],
            'username' => $data['username'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'     => $data['role'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET name = :name,
                 surname = :surname,
                 email = :email,
                 role = :role,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'name'    => $data['name'],
            'surname' => $data['surname'],
            'email'   => $data['email'],
            'role'    => $data['role'],
            'id'      => $id,
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        Database::connection()->prepare(
            'UPDATE users SET active = :active, updated_at = NOW() WHERE id = :id'
        )->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function changePassword(int $id, string $newPassword): void
    {
        Database::connection()->prepare(
            'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id'
        )->execute([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id'       => $id,
        ]);
    }

    public function emailOrUsernameExists(string $email, string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE (email = :email OR username = :username)';
        $params = ['email' => $email, 'username' => $username];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        return (int) Database::connection()->prepare($sql)->execute($params) && true
            ? (int) Database::connection()->prepare($sql)->execute($params) === 1
            : false;
    }

    public function isEmailTaken(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsernameTaken(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
