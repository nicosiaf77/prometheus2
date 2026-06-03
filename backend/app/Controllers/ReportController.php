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

        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Report</h1>
                    <p>Esportazioni operative del registro.</p>
                </div>
            </div>
            <div class="panel">
                <h2>Controlli filtrati</h2>
                <form method="get" action="/reports/controls.csv" class="filter-form">
                    <input class="form-control form-control-sm" name="registry_year" value="{$this->e((string) date('Y'))}" placeholder="Anno">
                    <input class="form-control form-control-sm" type="date" name="date_from">
                    <input class="form-control form-control-sm" type="date" name="date_to">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Stato</option>
                        <option value="bozza">Bozza</option>
                        <option value="validato">Validato</option>
                        <option value="annullato">Annullato</option>
                    </select>
                    <select class="form-select form-select-sm" name="outcome">
                        <option value="">Esito</option>
                        <option value="positivo">Positivo</option>
                        <option value="negativo">Negativo</option>
                        <option value="in_accertamento">In accertamento</option>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Scarica CSV</button>
                </form>
            </div>
        </main>
        HTML;

        return $this->view('Report', $content);
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
            return new Response('Esportazione non riuscita: ' . $this->e($exception->getMessage()), 500);
        }

        return new Response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $export['file_name'] . '"',
        ]);
    }
}
