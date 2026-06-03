<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Request;
use Prometheus\Core\Controller;
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
        $stats = (new StatisticsService())->dashboard($filters);
        $summary = $stats['summary'];
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Statistiche</h1>
                    <p>Indicatori operativi sui controlli amministrativi.</p>
                </div>
            </div>
            <div class="panel mb-3">
                <form method="get" action="/statistics" class="filter-form">
                    <input class="form-control form-control-sm" name="year" value="{$this->e($filters['year'])}" placeholder="Anno">
                    <select class="form-select form-select-sm" name="month">
                        {$this->monthOptions($filters['month'])}
                    </select>
                    <input class="form-control form-control-sm" type="date" name="date_from" value="{$this->e($filters['date_from'])}">
                    <input class="form-control form-control-sm" type="date" name="date_to" value="{$this->e($filters['date_to'])}">
                    <select class="form-select form-select-sm" name="outcome">
                        {$this->option('', 'Esito', $filters['outcome'])}
                        {$this->option('positivo', 'Positivo', $filters['outcome'])}
                        {$this->option('negativo', 'Negativo', $filters['outcome'])}
                        {$this->option('in_accertamento', 'In accertamento', $filters['outcome'])}
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Filtra</button>
                    <a class="btn btn-sm btn-outline-secondary" href="/statistics">Reset</a>
                </form>
            </div>
            <div class="metrics">
                <article><span>Controlli totali</span><strong>{$this->e($summary['total_controls'] ?? 0)}</strong></article>
                <article><span>Positivi</span><strong>{$this->e($summary['positive_controls'] ?? 0)}</strong></article>
                <article><span>Negativi</span><strong>{$this->e($summary['negative_controls'] ?? 0)}</strong></article>
                <article><span>In accertamento</span><strong>{$this->e($summary['investigation_controls'] ?? 0)}</strong></article>
                <article><span>Controlli sfusi</span><strong>{$this->e($summary['loose_controls'] ?? 0)}</strong></article>
                <article><span>Controlli evento</span><strong>{$this->e($summary['event_controls'] ?? 0)}</strong></article>
                <article><span>Sanzioni</span><strong>EUR {$this->money($summary['total_sanctions'] ?? 0)}</strong></article>
                <article><span>CNR</span><strong>{$this->e($summary['cnr_numbers'] ?? 0)}</strong></article>
                <article><span>Sequestri amm.</span><strong>{$this->e($summary['administrative_seizures'] ?? 0)}</strong></article>
                <article><span>Sequestri pen.</span><strong>{$this->e($summary['criminal_seizures'] ?? 0)}</strong></article>
                <article><span>Ritiri armi</span><strong>{$this->e($summary['weapon_withdrawals'] ?? 0)}</strong></article>
            </div>
            <div class="detail-grid">
                {$this->table('Per categoria', ['Categoria', 'Totale', 'Sanzioni'], $stats['by_category'], ['name', 'total', 'sanctions'])}
                {$this->table('Per agente', ['Agente', 'Totale'], $stats['by_agent'], ['name', 'total'])}
                {$this->table('Per evento', ['Evento', 'Totale', 'Sanzioni'], $stats['by_event'], ['name', 'total', 'sanctions'])}
            </div>
        </main>
        HTML;

        return $this->view('Statistiche', $content);
    }

    private function option(string $value, string $label, string $selected): string
    {
        $selectedAttribute = $value === $selected ? ' selected' : '';

        return '<option value="' . $this->e($value) . '"' . $selectedAttribute . '>' . $this->e($label) . '</option>';
    }

    private function monthOptions(string $selected): string
    {
        $options = $this->option('', 'Mese', $selected);

        for ($month = 1; $month <= 12; $month++) {
            $value = (string) $month;
            $options .= $this->option($value, str_pad($value, 2, '0', STR_PAD_LEFT), $selected);
        }

        return $options;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function table(string $title, array $headers, array $rows, array $keys): string
    {
        $head = '';
        foreach ($headers as $header) {
            $head .= '<th>' . $this->e($header) . '</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($keys as $key) {
                $value = str_contains($key, 'sanctions') ? 'EUR ' . $this->money($row[$key] ?? 0) : $this->e($row[$key] ?? '');
                $body .= '<td>' . $value . '</td>';
            }
            $body .= '</tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="' . count($headers) . '" class="text-muted">Nessun dato.</td></tr>';
        }

        return <<<HTML
        <section class="panel">
            <h2>{$this->e($title)}</h2>
            <table class="table table-sm align-middle">
                <thead><tr>{$head}</tr></thead>
                <tbody>{$body}</tbody>
            </table>
        </section>
        HTML;
    }
}
