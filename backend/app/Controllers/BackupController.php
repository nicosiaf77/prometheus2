<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\BackupService;
use Throwable;

final class BackupController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $backups = (new BackupService())->latest();
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-success py-2">' . $this->e($flash) . '</div>' : '';
        $rows = '';

        foreach ($backups as $backup) {
            $rows .= '<tr>'
                . '<td>' . $this->e($backup['file_name']) . '</td>'
                . '<td><code>' . $this->e($backup['file_hash']) . '</code></td>'
                . '<td>' . $this->e($backup['username'] ?? '') . '</td>'
                . '<td>' . $this->e($backup['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="text-muted">Nessun backup registrato.</td></tr>';
        }

        $csrf = $this->csrfField();
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Backup</h1>
                    <p>Backup database riservato agli amministratori.</p>
                </div>
                <form method="post" action="/backup">{$csrf}<button class="btn btn-sm btn-primary" type="submit">Crea backup</button></form>
            </div>
            {$flashHtml}
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>File</th><th>SHA-256</th><th>Utente</th><th>Data</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Backup', $content);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();

        try {
            $backup = (new BackupService())->create((int) $user['id'], dirname(__DIR__, 2));
            $this->flash('Backup creato: ' . $backup['file_name']);
        } catch (Throwable $exception) {
            $this->flash('Backup non riuscito: ' . $exception->getMessage());
        }

        return $this->redirect('/backup');
    }
}
