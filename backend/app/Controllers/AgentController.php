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

        $agents = (new AgentService())->all();
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-success py-2">' . $this->e($flash) . '</div>' : '';
        $rows = '';

        foreach ($agents as $agent) {
            $status = (int) $agent['active'] === 1 ? 'Attivo' : 'Disattivo';
            $rows .= '<tr>'
                . '<td>' . $this->e($agent['surname']) . '</td>'
                . '<td>' . $this->e($agent['name']) . '</td>'
                . '<td>' . $this->e($agent['rank'] ?? '') . '</td>'
                . '<td>' . $this->e($agent['office'] ?? '') . '</td>'
                . '<td>' . $status . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="text-muted">Nessun agente inserito.</td></tr>';
        }

        $csrf = $this->csrfField();
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Agenti</h1>
                    <p>Anagrafica agenti operanti.</p>
                </div>
            </div>
            {$flashHtml}
            <div class="panel mb-3">
                <form method="post" action="/agents" class="compact-form">
                    {$csrf}
                    <input class="form-control form-control-sm" name="surname" placeholder="Cognome" required>
                    <input class="form-control form-control-sm" name="name" placeholder="Nome" required>
                    <input class="form-control form-control-sm" name="rank" placeholder="Qualifica">
                    <input class="form-control form-control-sm" name="office" placeholder="Ufficio">
                    <button class="btn btn-sm btn-primary" type="submit">Aggiungi</button>
                </form>
            </div>
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Cognome</th><th>Nome</th><th>Qualifica</th><th>Ufficio</th><th>Stato</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Agenti', $content);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $data = [
            'name' => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'rank' => $request->input('rank', '') ?? '',
            'office' => $request->input('office', '') ?? '',
        ];

        if ($data['name'] === '' || $data['surname'] === '') {
            $this->flash('Nome e cognome agente sono obbligatori.');

            return $this->redirect('/agents');
        }

        (new AgentService())->create($data);
        (new AuditService())->record(AuditActions::AGENT_CREATED, 'agents', null, 'Agente creato: ' . $data['surname'] . ' ' . $data['name']);
        $this->flash('Agente aggiunto correttamente.');

        return $this->redirect('/agents');
    }
}
