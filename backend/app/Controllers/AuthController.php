<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return $this->json([
            'ok' => true,
            'message' => 'Backend API Prometheus2. Usa POST /login con identifier, password e _csrf_token.',
            'authenticated' => $this->auth()->check(),
            'csrf_token_endpoint' => '/csrf-token',
        ]);
    }

    public function csrfToken(): Response
    {
        return $this->json([
            'ok' => true,
            'csrf_token' => Session::csrfToken(),
        ]);
    }

    public function me(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'user' => $this->auth()->user(),
        ]);
    }

    public function login(): Response
    {
        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $identifier = $request->input('identifier', '') ?? '';
        $password = $request->input('password', '') ?? '';

        if ($identifier === '' || $password === '' || !$this->auth()->login($identifier, $password)) {
            return $this->error('Credenziali non valide.', 401);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Login effettuato.',
            'user' => $this->auth()->user(),
        ]);
    }

    public function logout(): Response
    {
        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $this->auth()->logout();

        return $this->json([
            'ok' => true,
            'message' => 'Logout effettuato.',
        ]);
    }
}
