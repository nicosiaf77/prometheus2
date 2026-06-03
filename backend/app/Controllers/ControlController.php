<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class ControlController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->view('Ricerca controlli', '<main class="container py-4"><h1>Ricerca controlli</h1><p>Modulo previsto nella fase 4.</p></main>');
    }

    public function create(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        return $this->view('Nuovo controllo', '<main class="container py-4"><h1>Nuovo controllo</h1><p>Modulo previsto nella fase 3.</p></main>');
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        return new Response('Operazione non ancora disponibile.', 501);
    }
}
