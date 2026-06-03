<?php

declare(strict_types=1);

namespace Prometheus\Core;

use Prometheus\Middleware\RoleMiddleware;
use Prometheus\Services\AuthService;

abstract class Controller
{
    protected function auth(): AuthService
    {
        return new AuthService();
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function error(string $message, int $status = 400, array $extra = []): Response
    {
        return $this->json(['ok' => false, 'error' => $message, 'code' => $status] + $extra, $status);
    }

    protected function validationError(array $errors): Response
    {
        return $this->error('Validazione non riuscita.', 422, ['errors' => $errors]);
    }

    protected function flash(?string $message = null): ?string
    {
        if ($message !== null) {
            Session::put('flash_message', $message);

            return null;
        }

        $current = Session::get('flash_message');
        Session::forget('flash_message');

        return is_string($current) ? $current : null;
    }

    protected function requireAuth(): ?Response
    {
        if ($this->auth()->check()) {
            return null;
        }

        return $this->error('Autenticazione richiesta.', 401);
    }

    protected function requireRoles(array $roles): ?Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        $user = $this->auth()->user();
        $role = is_array($user) ? (string) ($user['role'] ?? '') : '';

        if ((new RoleMiddleware())->allows($role, $roles)) {
            return null;
        }

        return $this->error('Permessi insufficienti.', 403, ['required_roles' => $roles]);
    }
}
