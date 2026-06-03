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

        $events = (new EventService())->all();
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-success py-2">' . $this->e($flash) . '</div>' : '';
        $rows = '';

        foreach ($events as $event) {
            $rows .= '<tr>'
                . '<td>' . $this->e($event['name']) . '</td>'
                . '<td>' . $this->e($event['created_by_username'] ?? '') . '</td>'
                . '<td>' . $this->e($event['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="text-muted">Nessun evento inserito.</td></tr>';
        }

        $csrf = $this->csrfField();
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Eventi</h1>
                    <p>Gestione semplice eventi e servizi speciali.</p>
                </div>
            </div>
            {$flashHtml}
            <div class="panel mb-3">
                <form method="post" action="/events" class="compact-form">
                    {$csrf}
                    <input class="form-control form-control-sm" name="name" placeholder="Nome evento" required>
                    <button class="btn btn-sm btn-primary" type="submit">Aggiungi</button>
                </form>
            </div>
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Nome</th><th>Creato da</th><th>Creato il</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Eventi', $content);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $name = $request->input('name', '') ?? '';
        $user = $this->auth()->user();

        if ($name === '' || $user === null) {
            $this->flash('Nome evento obbligatorio.');

            return $this->redirect('/events');
        }

        (new EventService())->create($name, (int) $user['id']);
        (new AuditService())->record(AuditActions::EVENT_CREATED, 'events', null, 'Evento creato: ' . $name);
        $this->flash('Evento aggiunto correttamente.');

        return $this->redirect('/events');
    }
}
