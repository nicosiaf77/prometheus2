<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;
use Prometheus\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'user' => $this->auth()->user(),
            'summary' => (new DashboardService())->summary(),
        ]);
    }
}
