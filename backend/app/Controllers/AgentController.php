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
use Throwable;

final class AgentController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok'     => true,
            'agents' => (new AgentService())->all(),
        ]);
    }

    public function show(string $agent): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        if (!ctype_digit($agent)) {
            return $this->error('Agente non trovato.', 404);
        }

        $agentData = (new AgentService())->find((int) $agent);

        if ($agentData === null) {
            return $this->error('Agente non trovato.', 404);
        }

        return $this->json(['ok' => true, 'agent' => $agentData]);
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
            'name'    => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'rank'    => $request->input('rank', '') ?? '',
            'office'  => $request->input('office', '') ?? '',
        ];

        if ($data['name'] === '' || $data['surname'] === '') {
            return $this->validationError([
                'name'    => $data['name'] === '' ? ['Il nome è obbligatorio.'] : [],
                'surname' => $data['surname'] === '' ? ['Il cognome è obbligatorio.'] : [],
            ]);
        }

        try {
            $agentId = (new AgentService())->create($data);
            (new AuditService())->record(AuditActions::AGENT_CREATED, 'agents', $agentId, 'Agente creato: ' . $data['surname'] . ' ' . $data['name']);
        } catch (Throwable $exception) {
            return $this->error('Creazione agente non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok'       => true,
            'message'  => 'Agente aggiunto correttamente.',
            'agent_id' => $agentId,
        ], 201);
    }

    public function update(string $agent): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($agent)) {
            return $this->error('Agente non trovato.', 404);
        }

        $agentId = (int) $agent;
        $service = new AgentService();

        if ($service->find($agentId) === null) {
            return $this->error('Agente non trovato.', 404);
        }

        $data = [
            'name'    => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'rank'    => $request->input('rank', '') ?? '',
            'office'  => $request->input('office', '') ?? '',
        ];

        if ($data['name'] === '' || $data['surname'] === '') {
            return $this->validationError([
                'name'    => $data['name'] === '' ? ['Il nome è obbligatorio.'] : [],
                'surname' => $data['surname'] === '' ? ['Il cognome è obbligatorio.'] : [],
            ]);
        }

        try {
            $service->update($agentId, $data);
            (new AuditService())->record(AuditActions::AGENT_CREATED, 'agents', $agentId, 'Agente aggiornato: ' . $data['surname'] . ' ' . $data['name']);
        } catch (Throwable $exception) {
            return $this->error('Aggiornamento agente non riuscito: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Agente aggiornato correttamente.']);
    }

    public function deactivate(string $agent): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($agent)) {
            return $this->error('Agente non trovato.', 404);
        }

        $agentId = (int) $agent;
        $service = new AgentService();

        if ($service->find($agentId) === null) {
            return $this->error('Agente non trovato.', 404);
        }

        try {
            $service->setActive($agentId, false);
            (new AuditService())->record(AuditActions::AGENT_CREATED, 'agents', $agentId, 'Agente disattivato id ' . $agentId);
        } catch (Throwable $exception) {
            return $this->error('Disattivazione non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Agente disattivato correttamente.']);
    }
}
