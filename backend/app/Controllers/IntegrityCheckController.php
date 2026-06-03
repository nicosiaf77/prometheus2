<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Models\User;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\IntegrityCheckService;

final class IntegrityCheckController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles([User::ROLE_ADMIN, User::ROLE_MANAGER])) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'message' => 'Usa POST /integrity-check per eseguire la verifica di integrita registro.',
            'checks' => [
                'presenza versioni',
                'hash corrente',
                'catena previous_hash',
            ],
        ]);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles([User::ROLE_ADMIN, User::ROLE_MANAGER])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();
        $result = (new IntegrityCheckService())->run((int) $user['id']);

        return $this->json([
            'ok' => $result['issues'] === [],
            'result' => $result,
        ]);
    }
}
