<?php
declare(strict_types=1);
namespace Prometheus\Services;

use Prometheus\Core\Database;

final class ReportService
{
    public function controlsCsv(array $filters, int $userId): array
    {
        $controls = (new ControlService())->search($filters, 1000);
        $fileName = 'controls_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Impossibile creare CSV temporaneo.');
        }

        $this->writeCsvRow($handle, [
            'registro',
            'anno',
            'data',
            'evento',
            'attivita',
            'luogo',
            'esito',
            'stato',
            'sanzione',
        ]);

        foreach ($controls as $control) {
            $this->writeCsvRow($handle, [
                $control['registry_number'],
                $control['registry_year'],
                $control['control_date'],
                $control['event_name'],
                $control['business_name'],
                $control['business_location'],
                $control['outcome'],
                $control['status'],
                $control['total_sanction_amount'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('Impossibile leggere CSV temporaneo.');
        }

        $this->recordExport($userId, 'controls_csv', $filters, $fileName);
        (new AuditService())->record(AuditActions::REPORT_EXPORTED, 'exports', null, 'Esportazione CSV controlli: ' . $fileName);

        return [
            'file_name' => $fileName,
            'content' => $csv,
        ];
    }

    private function recordExport(int $userId, string $type, array $filters, string $fileName): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO exports (user_id, export_type, filters_json, file_name, created_at)
             VALUES (:user_id, :export_type, :filters_json, :file_name, NOW())'
        );
        $statement->execute([
            'user_id' => $userId,
            'export_type' => $type,
            'filters_json' => json_encode($filters, JSON_THROW_ON_ERROR),
            'file_name' => $fileName,
        ]);
    }

    /**
     * @param resource $handle
     */
    private function writeCsvRow(mixed $handle, array $row): void
    {
        fputcsv($handle, $row, ',', '"', '', "\n");
    }
}
