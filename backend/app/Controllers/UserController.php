<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class UserController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        return $this->view('Utenti', '<main class="container py-4"><h1>Utenti</h1><p>Gestione utenti riservata agli amministratori.</p></main>');
    }
}
