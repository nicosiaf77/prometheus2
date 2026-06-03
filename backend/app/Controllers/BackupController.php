<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class BackupController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        return $this->view('Backup', '<main class="container py-4"><h1>Backup</h1><p>Funzione riservata ad amministratore.</p></main>');
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        return new Response('Backup non ancora disponibile.', 501);
    }
}
