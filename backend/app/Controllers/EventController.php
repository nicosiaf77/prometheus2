<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Models\User;
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

    public function show(string $event): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        if (!ctype_digit($event)) {
            return $this->error('Evento non trovato.', 404);
        }

        $eventData = (new EventService())->find((int) $event);

        if ($eventData === null) {
            return $this->error('Evento non trovato.', 404);
        }

        return $this->json(['ok' => true, 'event' => $eventData]);
    }

    public function update(string $event): Response
    {
        if ($response = $this->requireRoles([User::ROLE_ADMIN, User::ROLE_MANAGER])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($event)) {
            return $this->error('Evento non trovato.', 404);
        }

        $eventId = (int) $event;
        $service = new EventService();

        if ($service->find($eventId) === null) {
            return $this->error('Evento non trovato.', 404);
        }

        $name = trim($request->input('name', '') ?? '');

        if ($name === '') {
            return $this->validationError(['name' => ['Il nome evento è obbligatorio.']]);
        }

        if ($service->isNameTaken($name, $eventId)) {
            return $this->validationError(['name' => ['Nome evento già in uso.']]);
        }

        $service->update($eventId, $name);
        (new AuditService())->record(AuditActions::EVENT_CREATED, 'events', $eventId, 'Evento rinominato: ' . mb_strtoupper(trim($name), 'UTF-8'));

        return $this->json(['ok' => true, 'message' => 'Evento aggiornato correttamente.']);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles([User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR])) {
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
