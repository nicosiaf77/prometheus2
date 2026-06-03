<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Services\StatisticsService;

final class StatisticsController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        $request = new Request();
        $filters = [
            'year' => $request->input('year', (string) date('Y')) ?? (string) date('Y'),
            'month' => $request->input('month', '') ?? '',
            'date_from' => $request->input('date_from', '') ?? '',
            'date_to' => $request->input('date_to', '') ?? '',
            'outcome' => $request->input('outcome', '') ?? '',
        ];

        return $this->json([
            'ok' => true,
            'filters' => $filters,
            'statistics' => (new StatisticsService())->dashboard($filters),
        ]);
    }
}
