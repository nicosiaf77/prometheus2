<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\AuditActions;
use Prometheus\Services\AuditService;
use Prometheus\Services\EventService;

final class EventController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'events' => (new EventService())->all(),
        ]);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $name = $request->input('name', '') ?? '';
        $user = $this->auth()->user();

        if ($name === '' || $user === null) {
            return $this->error('Nome evento obbligatorio.', 422);
        }

        (new EventService())->create($name, (int) $user['id']);
        (new AuditService())->record(AuditActions::EVENT_CREATED, 'events', null, 'Evento creato: ' . $name);

        return $this->json([
            'ok' => true,
            'message' => 'Evento aggiunto correttamente.',
        ], 201);
    }
}
