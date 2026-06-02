<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class EventController extends Controller
{
    public function index(): Response
    {
        return $this->view('Eventi', '<main class="container py-4"><h1>Eventi</h1><p>Gestione semplice eventi e servizi speciali.</p></main>');
    }
}
