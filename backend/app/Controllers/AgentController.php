<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class AgentController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->view('Agenti', '<main class="container py-4"><h1>Agenti</h1><p>Anagrafica agenti operanti.</p></main>');
    }
}
