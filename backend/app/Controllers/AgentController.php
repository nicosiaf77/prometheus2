<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\AgentService;
use Prometheus\Services\AuditActions;
use Prometheus\Services\AuditService;

final class AgentController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'agents' => (new AgentService())->all(),
        ]);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $data = [
            'name' => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'rank' => $request->input('rank', '') ?? '',
            'office' => $request->input('office', '') ?? '',
        ];

        if ($data['name'] === '' || $data['surname'] === '') {
            return $this->error('Nome e cognome agente sono obbligatori.', 422);
        }

        (new AgentService())->create($data);
        (new AuditService())->record(AuditActions::AGENT_CREATED, 'agents', null, 'Agente creato: ' . $data['surname'] . ' ' . $data['name']);

        return $this->json([
            'ok' => true,
            'message' => 'Agente aggiunto correttamente.',
        ], 201);
    }
}
