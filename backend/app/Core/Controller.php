<?php

declare(strict_types=1);

namespace Prometheus\Core;

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

    protected function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    protected function csrfField(): string
    {
        $token = Session::csrfToken();

        return '<input type="hidden" name="_csrf_token" value="' . $this->e($token) . '">';
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

        return Response::redirect('/login');
    }

    protected function requireRoles(array $roles): ?Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        if ($this->auth()->hasRole($roles)) {
            return null;
        }

        return new Response(
            '<main class="container py-4"><h1>Accesso negato</h1><p>Non disponi dei permessi necessari per questa funzione.</p></main>',
            403
        );
    }

    protected function view(string $title, string $content): Response
    {
        $body = <<<HTML
        <!doctype html>
        <html lang="it">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title} - Prometheus2</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="/assets/css/app.css" rel="stylesheet">
        </head>
        <body>
            {$content}
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        HTML;

        return new Response($body);
    }
}
