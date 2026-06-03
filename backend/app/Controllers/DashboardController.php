<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        $user = $this->auth()->user();
        $summary = (new DashboardService())->summary();
        $content = file_get_contents(dirname(__DIR__, 2) . '/resources/dashboard.html') ?: '<main>Prometheus2</main>';
        $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')) ?: ($user['username'] ?? 'Utente');
        $content = str_replace(
            [
                '{{USER_NAME}}',
                '{{CSRF_TOKEN}}',
                '{{YEAR_CONTROLS}}',
                '{{MONTH_CONTROLS}}',
                '{{POSITIVE_CONTROLS}}',
                '{{NEGATIVE_CONTROLS}}',
                '{{INVESTIGATION_CONTROLS}}',
                '{{DRAFT_CONTROLS}}',
                '{{YEAR_SANCTIONS}}',
                '{{LATEST_CONTROLS_ROWS}}',
            ],
            [
                htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
                Session::csrfToken(),
                (string) $summary['year_controls'],
                (string) $summary['month_controls'],
                (string) $summary['positive_controls'],
                (string) $summary['negative_controls'],
                (string) $summary['investigation_controls'],
                (string) $summary['draft_controls'],
                number_format((float) $summary['year_sanctions'], 2, ',', '.'),
                $this->latestRows($summary['latest_controls']),
            ],
            $content
        );

        return $this->view('Dashboard', $content);
    }

    private function latestRows(array $controls): string
    {
        if ($controls === []) {
            return '<tr><td colspan="6" class="text-muted">Nessun controllo inserito.</td></tr>';
        }

        $rows = [];

        foreach ($controls as $control) {
            $registry = htmlspecialchars($control['registry_number'] . '/' . $control['registry_year'], ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars((string) $control['control_date'], ENT_QUOTES, 'UTF-8');
            $event = htmlspecialchars((string) $control['event_name'], ENT_QUOTES, 'UTF-8');
            $business = htmlspecialchars((string) $control['business_name'], ENT_QUOTES, 'UTF-8');
            $outcome = htmlspecialchars((string) $control['outcome'], ENT_QUOTES, 'UTF-8');
            $status = htmlspecialchars((string) $control['status'], ENT_QUOTES, 'UTF-8');
            $rows[] = "<tr><td>{$registry}</td><td>{$date}</td><td>{$event}</td><td>{$business}</td><td>{$outcome}</td><td>{$status}</td></tr>";
        }

        return implode('', $rows);
    }
}
