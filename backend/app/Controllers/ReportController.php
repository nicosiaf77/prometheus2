<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Services\ReportService;
use Throwable;

final class ReportController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'exports' => [
                [
                    'name' => 'Controlli filtrati',
                    'method' => 'GET',
                    'endpoint' => '/reports/controls.csv',
                    'format' => 'text/csv',
                    'filters' => [
                        'registry_number',
                        'registry_year',
                        'date_from',
                        'date_to',
                        'has_event',
                        'event_name',
                        'business_name',
                        'business_location',
                        'category_id',
                        'agent_id',
                        'outcome',
                        'status',
                        'sanction_presence',
                    ],
                ],
            ],
        ]);
    }

    public function controlsCsv(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $user = $this->auth()->user();
        $request = new Request();
        $filters = [
            'registry_number' => $request->input('registry_number', '') ?? '',
            'registry_year' => $request->input('registry_year', '') ?? '',
            'date_from' => $request->input('date_from', '') ?? '',
            'date_to' => $request->input('date_to', '') ?? '',
            'has_event' => $request->input('has_event', '') ?? '',
            'event_name' => $request->input('event_name', '') ?? '',
            'business_name' => $request->input('business_name', '') ?? '',
            'business_location' => $request->input('business_location', '') ?? '',
            'category_id' => $request->input('category_id', '') ?? '',
            'agent_id' => $request->input('agent_id', '') ?? '',
            'outcome' => $request->input('outcome', '') ?? '',
            'status' => $request->input('status', '') ?? '',
            'sanction_presence' => $request->input('sanction_presence', '') ?? '',
        ];

        try {
            $export = (new ReportService())->controlsCsv($filters, (int) $user['id']);
        } catch (Throwable $exception) {
            return $this->error('Esportazione non riuscita: ' . $exception->getMessage(), 500);
        }

        return new Response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $export['file_name'] . '"',
        ]);
    }
}
