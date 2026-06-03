<?php

declare(strict_types=1);

namespace Prometheus\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(Env::get('SESSION_NAME', 'prometheus2_session') ?? 'prometheus2_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => Env::get('SESSION_SAMESITE', 'Lax') ?? 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function csrfToken(): string
    {
        $token = self::get('_csrf_token');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            self::put('_csrf_token', $token);
        }

        return $token;
    }

    public static function validateCsrf(?string $token): bool
    {
        $current = self::get('_csrf_token');

        return is_string($current) && is_string($token) && hash_equals($current, $token);
    }
}
