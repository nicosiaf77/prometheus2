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
            'ok'      => true,
            'exports' => [
                ['name' => 'Controlli CSV',   'method' => 'GET', 'endpoint' => '/reports/controls.csv',  'format' => 'text/csv'],
                ['name' => 'Controlli Excel',  'method' => 'GET', 'endpoint' => '/reports/controls.xlsx', 'format' => 'application/vnd.ms-excel'],
                ['name' => 'Controlli PDF',    'method' => 'GET', 'endpoint' => '/reports/controls.pdf',  'format' => 'application/pdf'],
            ],
            'filters' => [
                'registry_number', 'registry_year', 'date_from', 'date_to',
                'has_event', 'event_name', 'business_name', 'business_location',
                'category_id', 'agent_id', 'outcome', 'status', 'sanction_presence',
            ],
        ]);
    }

    public function controlsCsv(): Response
    {
        return $this->exportResponse('controlsCsv', 'text/csv; charset=UTF-8');
    }

    public function controlsXlsx(): Response
    {
        return $this->exportResponse('controlsXlsx', 'application/vnd.ms-excel');
    }

    public function controlsPdf(): Response
    {
        return $this->exportResponse('controlsPdf', 'application/pdf');
    }

    private function exportResponse(string $method, string $contentType): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $user    = $this->auth()->user();
        $filters = $this->filters();

        try {
            $export = (new ReportService())->{$method}($filters, (int) $user['id']);
        } catch (Throwable $exception) {
            return $this->error('Esportazione non riuscita: ' . $exception->getMessage(), 500);
        }

        return new Response($export['content'], 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $export['file_name'] . '"',
        ]);
    }

    private function filters(): array
    {
        $request = new Request();

        return [
            'registry_number'  => $request->input('registry_number', '') ?? '',
            'registry_year'    => $request->input('registry_year', '') ?? '',
            'date_from'        => $request->input('date_from', '') ?? '',
            'date_to'          => $request->input('date_to', '') ?? '',
            'has_event'        => $request->input('has_event', '') ?? '',
            'event_name'       => $request->input('event_name', '') ?? '',
            'business_name'    => $request->input('business_name', '') ?? '',
            'business_location'=> $request->input('business_location', '') ?? '',
            'category_id'      => $request->input('category_id', '') ?? '',
            'agent_id'         => $request->input('agent_id', '') ?? '',
            'outcome'          => $request->input('outcome', '') ?? '',
            'status'           => $request->input('status', '') ?? '',
            'sanction_presence'=> $request->input('sanction_presence', '') ?? '',
        ];
    }
}
