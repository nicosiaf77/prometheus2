<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;
use Prometheus\Services\AuditLogService;

final class AuditLogController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $logs = (new AuditLogService())->latest();
        $rows = '';

        foreach ($logs as $log) {
            $rows .= '<tr>'
                . '<td>' . $this->e($log['created_at']) . '</td>'
                . '<td>' . $this->e($log['username'] ?? '') . '</td>'
                . '<td>' . $this->e($log['action']) . '</td>'
                . '<td>' . $this->e($log['entity_type'] ?? '') . '</td>'
                . '<td>' . $this->e($log['entity_id'] ?? '') . '</td>'
                . '<td>' . $this->e($log['ip_address'] ?? '') . '</td>'
                . '<td>' . $this->e($log['description'] ?? '') . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="text-muted">Nessun log presente.</td></tr>';
        }

        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Audit log</h1>
                    <p>Ultime operazioni rilevanti registrate dal sistema.</p>
                </div>
            </div>
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Data</th><th>Utente</th><th>Azione</th><th>Entita</th><th>ID</th><th>IP</th><th>Descrizione</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Audit log', $content);
    }
}
