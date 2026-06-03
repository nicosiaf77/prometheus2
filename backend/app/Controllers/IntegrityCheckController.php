<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class IntegrityCheckController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        return $this->view('Verifica integrita', '<main class="container py-4"><h1>Verifica integrita registro</h1><p>Controllo hash chain previsto nella fase sicurezza.</p></main>');
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        return new Response('Verifica non ancora disponibile.', 501);
    }
}
