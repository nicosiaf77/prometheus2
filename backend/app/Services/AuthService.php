<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\Request;
use Prometheus\Core\Session;
use PDO;

final class AuthService
{
    public function check(): bool
    {
        return is_int(Session::get('user_id')) || ctype_digit((string) Session::get('user_id', ''));
    }

    public function user(): ?array
    {
        $userId = Session::get('user_id');

        if ($userId === null) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, name, surname, email, username, role, active, last_login_at FROM users WHERE id = :id AND active = 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function login(string $identifier, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE (username = :username_identifier OR email = :email_identifier) AND active = 1 LIMIT 1'
        );
        $statement->execute([
            'username_identifier' => $identifier,
            'email_identifier' => $identifier,
        ]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        $success = is_array($user) && password_verify($password, (string) $user['password']);

        $this->recordLoginAttempt($identifier, $success, is_array($user) ? (int) $user['id'] : null);

        if (!$success || !is_array($user)) {
            (new AuditService())->record(AuditActions::LOGIN_FAILED, 'users', null, 'Tentativo login fallito per ' . $identifier);

            return false;
        }

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);

        Database::connection()
            ->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        (new AuditService())->record(AuditActions::LOGIN_SUCCESS, 'users', (int) $user['id'], 'Login riuscito');

        return true;
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');

        if ($userId !== null) {
            (new AuditService())->record(AuditActions::LOGOUT, 'users', (int) $userId, 'Logout');
        }

        Session::forget('user_id');
        Session::regenerate();
    }

    public function hasRole(array $roles): bool
    {
        $user = $this->user();

        return is_array($user) && in_array($user['role'], $roles, true);
    }

    private function recordLoginAttempt(string $identifier, bool $success, ?int $userId): void
    {
        $request = new Request();
        $statement = Database::connection()->prepare(
            'INSERT INTO login_logs (user_id, username_attempted, success, ip_address, user_agent, created_at)
             VALUES (:user_id, :username_attempted, :success, :ip_address, :user_agent, NOW())'
        );
        $statement->execute([
            'user_id' => $userId,
            'username_attempted' => $identifier,
            'success' => $success ? 1 : 0,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
