<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;

final class DashboardService
{
    public function summary(): array
    {
        $year = (int) date('Y');
        $month = (int) date('m');

        return [
            'year_controls' => $this->count('registry_year = :year', ['year' => $year]),
            'month_controls' => $this->count('YEAR(control_date) = :year AND MONTH(control_date) = :month', ['year' => $year, 'month' => $month]),
            'positive_controls' => $this->count('registry_year = :year AND outcome = :outcome', ['year' => $year, 'outcome' => 'positivo']),
            'negative_controls' => $this->count('registry_year = :year AND outcome = :outcome', ['year' => $year, 'outcome' => 'negativo']),
            'investigation_controls' => $this->count('registry_year = :year AND outcome = :outcome', ['year' => $year, 'outcome' => 'in_accertamento']),
            'draft_controls' => $this->count('status = :status', ['status' => 'bozza']),
            'year_sanctions' => $this->sumYearSanctions($year),
            'latest_controls' => $this->latestControls(),
        ];
    }

    private function count(string $where, array $params): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM controls WHERE {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function sumYearSanctions(int $year): float
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(total_sanction_amount), 0) FROM controls WHERE registry_year = :year'
        );
        $statement->execute(['year' => $year]);

        return (float) $statement->fetchColumn();
    }

    private function latestControls(): array
    {
        $statement = Database::connection()->query(
            "SELECT controls.registry_number,
                    controls.registry_year,
                    controls.control_date,
                    COALESCE(events.name, 'Nessuno') AS event_name,
                    controls.business_name,
                    controls.outcome,
                    controls.status
             FROM controls
             LEFT JOIN events ON events.id = controls.event_id
             ORDER BY controls.created_at DESC
             LIMIT 10"
        );

        return $statement->fetchAll();
    }
}
