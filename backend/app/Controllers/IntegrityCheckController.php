<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\IntegrityCheckService;

final class IntegrityCheckController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-info py-2">' . $this->e($flash) . '</div>' : '';
        $csrf = $this->csrfField();
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Verifica integrita registro</h1>
                    <p>Controllo coerenza hash e versioni dei controlli.</p>
                </div>
                <form method="post" action="/integrity-check">{$csrf}<button class="btn btn-sm btn-primary" type="submit">Esegui verifica</button></form>
            </div>
            {$flashHtml}
            <div class="panel">
                <p class="text-muted mb-0">La verifica controlla presenza versioni, hash corrente e collegamento `previous_hash` tra versioni.</p>
            </div>
        </main>
        HTML;

        return $this->view('Verifica integrita', $content);
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

        $user = $this->auth()->user();
        $result = (new IntegrityCheckService())->run((int) $user['id']);

        if ($result['issues'] === []) {
            $this->flash('Verifica completata: ' . $result['checked_controls'] . ' controlli integri.');
        } else {
            $this->flash('Verifica completata con ' . count($result['issues']) . ' anomalie: ' . implode(' | ', $result['issues']));
        }

        return $this->redirect('/integrity-check');
    }
}
