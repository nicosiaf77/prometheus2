<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/resources/dashboard.html') ?: '<main>Prometheus2</main>';

        return $this->view('Dashboard', $content);
    }
}
