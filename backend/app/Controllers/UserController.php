<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\AuditActions;
use Prometheus\Services\AuditService;
use Prometheus\Services\UserService;
use Throwable;

final class UserController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'users' => (new UserService())->all(),
        ]);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $data = [
            'name' => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'email' => $request->input('email', '') ?? '',
            'username' => $request->input('username', '') ?? '',
            'password' => $request->input('password', '') ?? '',
            'role' => $request->input('role', '') ?? '',
        ];

        if ($this->invalid($data)) {
            return $this->error('Tutti i campi utente sono obbligatori e il ruolo deve essere valido.', 422);
        }

        try {
            $userId = (new UserService())->create($data);
            (new AuditService())->record(AuditActions::USER_CREATED, 'users', $userId, 'Utente creato: ' . $data['username']);
        } catch (Throwable $exception) {
            return $this->error('Creazione utente non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Utente creato correttamente.',
            'user_id' => $userId,
        ], 201);
    }

    private function invalid(array $data): bool
    {
        $roles = ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'];

        return $data['name'] === ''
            || $data['surname'] === ''
            || $data['email'] === ''
            || $data['username'] === ''
            || $data['password'] === ''
            || !in_array($data['role'], $roles, true);
    }
}
