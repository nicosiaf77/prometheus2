<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\PdfWriter;

final class ReportService
{
    // ── CSV ───────────────────────────────────────────────────────────

    public function controlsCsv(array $filters, int $userId): array
    {
        $controls = (new ControlService())->search($filters, 1000);
        $fileName = 'controlli_' . date('Ymd_His') . '.csv';
        $handle   = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Impossibile creare CSV temporaneo.');
        }

        // BOM UTF-8 per compatibilità Excel su Windows
        fwrite($handle, "\xEF\xBB\xBF");

        $this->writeCsvRow($handle, [
            'Registro', 'Anno', 'Data', 'Ora', 'Evento',
            'Attività', 'Luogo', 'Esito', 'Stato', 'Sanzione (€)',
        ]);

        foreach ($controls as $row) {
            $this->writeCsvRow($handle, [
                $row['registry_number'],
                $row['registry_year'],
                $row['control_date'],
                $row['control_time'] ?? '',
                $row['event_name'],
                $row['business_name'],
                $row['business_location'],
                $row['outcome'],
                $row['status'],
                $row['total_sanction_amount'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('Impossibile leggere CSV temporaneo.');
        }

        $this->recordExport($userId, 'controls_csv', $filters, $fileName);
        (new AuditService())->record(AuditActions::REPORT_EXPORTED, 'exports', null, 'Export CSV: ' . $fileName);

        return ['file_name' => $fileName, 'content' => $csv];
    }

    // ── Excel SpreadsheetML ───────────────────────────────────────────

    public function controlsXls(array $filters, int $userId): array
    {
        $controls = (new ControlService())->search($filters, 1000);
        $fileName = 'controlli_' . date('Ymd_His') . '.xls';

        $headers = [
            'Registro', 'Anno', 'Data', 'Ora', 'Evento',
            'Attività', 'Luogo', 'Esito', 'Stato', 'Sanzione (€)',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Styles><Style ss:ID="H"><Font ss:Bold="1"/></Style></Styles>' . "\n";
        $xml .= '<Worksheet ss:Name="Controlli"><Table>' . "\n";

        // Riga intestazione
        $xml .= '<Row>';

        foreach ($headers as $h) {
            $xml .= '<Cell ss:StyleID="H"><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1) . '</Data></Cell>';
        }

        $xml .= '</Row>' . "\n";

        // Righe dati
        foreach ($controls as $row) {
            $cells = [
                (string) $row['registry_number'],
                (string) $row['registry_year'],
                (string) $row['control_date'],
                (string) ($row['control_time'] ?? ''),
                (string) $row['event_name'],
                (string) $row['business_name'],
                (string) $row['business_location'],
                (string) $row['outcome'],
                (string) $row['status'],
                (string) ($row['total_sanction_amount'] ?? ''),
            ];
            $xml .= '<Row>';

            foreach ($cells as $cell) {
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($cell, ENT_XML1) . '</Data></Cell>';
            }

            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';

        $this->recordExport($userId, 'controls_xls', $filters, $fileName);
        (new AuditService())->record(AuditActions::REPORT_EXPORTED, 'exports', null, 'Export Excel: ' . $fileName);

        return ['file_name' => $fileName, 'content' => $xml];
    }

    // ── PDF elenco ────────────────────────────────────────────────────

    public function controlsPdf(array $filters, int $userId): array
    {
        $controls = (new ControlService())->search($filters, 500);
        $fileName = 'controlli_' . date('Ymd_His') . '.pdf';

        $pdf = new PdfWriter();
        $pdf->title('Registro controlli amministrativi');
        $pdf->subtitle('Questura di Catania — Squadra Amministrativa');
        $pdf->paragraph('Generato il', date('d/m/Y H:i'));
        $pdf->paragraph('Totale record', (string) count($controls));
        $pdf->spacer(10);

        $headers = ['N.', 'Data', 'Evento', 'Attività', 'Luogo', 'Esito', 'Stato', 'Sanzione'];
        $widths  = [30, 52, 65, 95, 85, 55, 45, 55];

        $rows = array_map(static fn (array $r): array => [
            $r['registry_number'] . '/' . $r['registry_year'],
            $r['control_date'],
            $r['event_name'],
            $r['business_name'],
            $r['business_location'],
            $r['outcome'],
            $r['status'],
            $r['total_sanction_amount'] !== null ? '€ ' . number_format((float) $r['total_sanction_amount'], 2, ',', '.') : '',
        ], $controls);

        $pdf->table($headers, $rows, $widths);

        $content = $pdf->render();

        $this->recordExport($userId, 'controls_pdf', $filters, $fileName);
        (new AuditService())->record(AuditActions::REPORT_EXPORTED, 'exports', null, 'Export PDF elenco: ' . $fileName);

        return ['file_name' => $fileName, 'content' => $content];
    }

    // ── PDF scheda singolo controllo ──────────────────────────────────

    public function controlDetailPdf(array $control, int $userId): array
    {
        $fileName = 'controllo_' . ($control['registry_number'] ?? 0) . '_' . ($control['registry_year'] ?? date('Y')) . '.pdf';
        $pdf = new PdfWriter();

        $pdf->title('Scheda controllo amministrativo');
        $pdf->subtitle('N. ' . ($control['registry_number'] ?? '') . ' / ' . ($control['registry_year'] ?? ''));
        $pdf->spacer(6);

        $pdf->subtitle('Dati generali');
        $pdf->paragraph('Data', (string) ($control['control_date'] ?? ''));
        $pdf->paragraph('Ora', (string) ($control['control_time'] ?? ''));
        $pdf->paragraph('Evento', (string) ($control['event_name'] ?? 'Nessuno'));
        $pdf->paragraph('Stato', (string) ($control['status'] ?? ''));
        $pdf->paragraph('Esito', (string) ($control['outcome'] ?? ''));
        $pdf->spacer(6);

        $pdf->subtitle('Attività controllata');
        $pdf->paragraph('Nome attività', (string) ($control['business_name'] ?? ''));
        $pdf->paragraph('Luogo', (string) ($control['business_location'] ?? ''));

        if (!empty($control['business_owner'])) {
            $pdf->paragraph('Titolare', (string) $control['business_owner']);
        }

        if (!empty($control['offender'])) {
            $pdf->paragraph('Trasgressore', (string) $control['offender']);
        }

        $primaryCat = $control['primary_category']['name'] ?? '';

        if ($primaryCat !== '') {
            $pdf->paragraph('Categoria principale', $primaryCat);
        }

        $secondary = array_map(static fn (array $c): string => $c['name'], $control['secondary_categories'] ?? []);

        if ($secondary !== []) {
            $pdf->paragraph('Categorie secondarie', implode(', ', $secondary));
        }

        $agents = array_map(static fn (array $a): string => trim(($a['rank'] ?? '') . ' ' . $a['surname'] . ' ' . $a['name']), $control['agents'] ?? []);

        if ($agents !== []) {
            $pdf->paragraph('Agenti operanti', implode(', ', $agents));
        }

        $pdf->spacer(6);
        $pdf->subtitle('Violazioni e sanzioni');

        if (!empty($control['violated_rules'])) {
            $pdf->paragraph('Norme violate', (string) $control['violated_rules']);
        }

        if (!empty($control['sanctioning_rules'])) {
            $pdf->paragraph('Norme sanzionatrici', (string) $control['sanctioning_rules']);
        }

        if (!empty($control['total_sanction_amount'])) {
            $pdf->paragraph('Importo sanzione', '€ ' . number_format((float) $control['total_sanction_amount'], 2, ',', '.'));
        }

        if (!empty($control['alleged_crime'])) {
            $pdf->paragraph('Reato contestato', (string) $control['alleged_crime']);
        }

        if (!empty($control['cnr_number'])) {
            $pdf->paragraph('N. CNR', (string) $control['cnr_number']);
        }

        $pdf->spacer(6);
        $pdf->subtitle('Misure adottate');
        $pdf->paragraph('Sequestro amministrativo', $control['administrative_seizure'] ? 'Sì' : 'No');
        $pdf->paragraph('Sequestro penale', $control['criminal_seizure'] ? 'Sì' : 'No');
        $pdf->paragraph('Ritiro arma ex art. 39 TULPS', $control['weapon_precautionary_withdrawal'] ? 'Sì' : 'No');

        if (!empty($control['notes'])) {
            $pdf->spacer(6);
            $pdf->subtitle('Note operative');
            $pdf->paragraph('Note', (string) $control['notes']);
        }

        $pdf->spacer(6);
        $pdf->subtitle('Tracciabilità');
        $pdf->paragraph('Inserito da', (string) ($control['created_by_username'] ?? ''));
        $pdf->paragraph('Data inserimento', (string) ($control['created_at'] ?? ''));

        if (!empty($control['validated_by_username'])) {
            $pdf->paragraph('Validato da', (string) $control['validated_by_username']);
            $pdf->paragraph('Data validazione', (string) ($control['validated_at'] ?? ''));
        }

        if (!empty($control['annulled_by_username'])) {
            $pdf->paragraph('Annullato da', (string) $control['annulled_by_username']);
            $pdf->paragraph('Motivo annullamento', (string) ($control['annulment_reason'] ?? ''));
        }

        $content = $pdf->render();

        $this->recordExport($userId, 'control_detail_pdf', ['control_id' => $control['id'] ?? null], $fileName);
        (new AuditService())->record(AuditActions::REPORT_EXPORTED, 'exports', (int) ($control['id'] ?? 0), 'Export PDF scheda: ' . $fileName);

        return ['file_name' => $fileName, 'content' => $content];
    }

    // ── PDF statistiche ───────────────────────────────────────────────

    public function statisticsPdf(array $filters, int $userId): array
    {
        $stats    = (new StatisticsService())->dashboard($filters);
        $fileName = 'statistiche_' . date('Ymd_His') . '.pdf';
        $pdf      = new PdfWriter();

        $pdf->title('Statistiche controlli amministrativi');
        $pdf->subtitle('Questura di Catania — Squadra Amministrativa');
        $pdf->paragraph('Generato il', date('d/m/Y H:i'));

        $yearLabel = ($filters['year'] ?? '') !== '' ? $filters['year'] : date('Y');
        $pdf->paragraph('Anno di riferimento', (string) $yearLabel);
        $pdf->spacer(10);

        // Riepilogo generale
        $pdf->subtitle('Riepilogo generale');
        $s = $stats['summary'];
        $pdf->paragraph('Totale controlli', (string) ($s['total_controls'] ?? 0));
        $pdf->paragraph('Positivi', (string) ($s['positive_controls'] ?? 0));
        $pdf->paragraph('Negativi', (string) ($s['negative_controls'] ?? 0));
        $pdf->paragraph('In accertamento', (string) ($s['investigation_controls'] ?? 0));
        $pdf->paragraph('Controlli sfusi', (string) ($s['loose_controls'] ?? 0));
        $pdf->paragraph('Collegati a evento', (string) ($s['event_controls'] ?? 0));
        $pdf->paragraph('Totale sanzioni', '€ ' . number_format((float) ($s['total_sanctions'] ?? 0), 2, ',', '.'));
        $pdf->paragraph('Reati contestati', (string) ($s['alleged_crimes'] ?? 0));
        $pdf->paragraph('Comunicazioni CNR', (string) ($s['cnr_numbers'] ?? 0));
        $pdf->paragraph('Sequestri amministrativi', (string) ($s['administrative_seizures'] ?? 0));
        $pdf->paragraph('Sequestri penali', (string) ($s['criminal_seizures'] ?? 0));
        $pdf->paragraph('Ritiri armi art. 39 TULPS', (string) ($s['weapon_withdrawals'] ?? 0));
        $pdf->spacer(10);

        // Per categoria
        if (!empty($stats['by_category'])) {
            $pdf->subtitle('Controlli per categoria');
            $pdf->table(
                ['Categoria', 'Totale', 'Positivi', 'Negativi', 'Accertamento', 'Sanzioni (€)'],
                array_map(static fn (array $r): array => [
                    $r['name'],
                    $r['total'],
                    $r['positive'],
                    $r['negative'],
                    $r['investigation'],
                    number_format((float) $r['sanctions'], 2, ',', '.'),
                ], $stats['by_category']),
                [170, 45, 50, 50, 65, 65]
            );
            $pdf->spacer(10);
        }

        // Per agente
        if (!empty($stats['by_agent'])) {
            $pdf->subtitle('Controlli per agente');
            $pdf->table(
                ['Agente', 'Qualifica', 'Totale controlli'],
                array_map(static fn (array $r): array => [
                    $r['name'],
                    $r['rank'] ?? '',
                    $r['total'],
                ], $stats['by_agent']),
                [230, 170, 85]
            );
            $pdf->spacer(10);
        }

        // Per evento
        if (!empty($stats['by_event'])) {
            $pdf->subtitle('Controlli per evento / servizio speciale');
            $pdf->table(
                ['Evento', 'Totale', 'Sanzioni (€)'],
                array_map(static fn (array $r): array => [
                    $r['name'],
                    $r['total'],
                    number_format((float) $r['sanctions'], 2, ',', '.'),
                ], $stats['by_event']),
                [280, 80, 125]
            );
        }

        $content = $pdf->render();

        $this->recordExport($userId, 'statistics_pdf', $filters, $fileName);
        (new AuditService())->record(AuditActions::REPORT_EXPORTED, 'exports', null, 'Export PDF statistiche: ' . $fileName);

        return ['file_name' => $fileName, 'content' => $content];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function recordExport(int $userId, string $type, array $filters, string $fileName): void
    {
        Database::connection()->prepare(
            'INSERT INTO exports (user_id, export_type, filters_json, file_name, created_at)
             VALUES (:user_id, :export_type, :filters_json, :file_name, NOW())'
        )->execute([
            'user_id'      => $userId,
            'export_type'  => $type,
            'filters_json' => json_encode($filters, JSON_THROW_ON_ERROR),
            'file_name'    => $fileName,
        ]);
    }

    /** @param resource $handle */
    private function writeCsvRow(mixed $handle, array $row): void
    {
        fputcsv($handle, $row, ',', '"', '', "\n");
    }
}
