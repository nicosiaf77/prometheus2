<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class StatisticsController extends Controller
{
    public function index(): Response
    {
        return $this->view('Statistiche', '<main class="container py-4"><h1>Statistiche</h1><p>Dashboard statistica prevista nella fase 5.</p></main>');
    }
}
