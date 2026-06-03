<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class ReportController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        return $this->view('Report', '<main class="container py-4"><h1>Report</h1><p>Esportazioni PDF, CSV ed Excel.</p></main>');
    }
}
