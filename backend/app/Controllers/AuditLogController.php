<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class AuditLogController extends Controller
{
    public function index(): Response
    {
        return $this->view('Audit log', '<main class="container py-4"><h1>Audit log</h1><p>Consultazione riservata ad amministratore e responsabile ufficio.</p></main>');
    }
}
