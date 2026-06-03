<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use PDO;

final class StatisticsService
{
    public function dashboard(array $filters): array
    {
        [$whereSql, $params] = $this->where($filters);

        return [
            'summary'        => $this->summary($whereSql, $params),
            'by_category'    => $this->byCategory($whereSql, $params),
            'by_agent'       => $this->byAgent($whereSql, $params),
            'by_event'       => $this->byEvent($whereSql, $params),
        ];
    }

    // ── Riepilogo generale ────────────────────────────────────────────

    private function summary(string $whereSql, array $params): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                COUNT(*)                                                          AS total_controls,
                SUM(outcome = 'positivo')                                         AS positive_controls,
                SUM(outcome = 'negativo')                                         AS negative_controls,
                SUM(outcome = 'in_accertamento')                                  AS investigation_controls,
                SUM(has_event = 0)                                                AS loose_controls,
                SUM(has_event = 1)                                                AS event_controls,
                COALESCE(SUM(total_sanction_amount), 0)                           AS total_sanctions,
                SUM(alleged_crime IS NOT NULL AND alleged_crime <> '')            AS alleged_crimes,
                SUM(cnr_number_encrypted IS NOT NULL AND cnr_number_encrypted <> '') AS cnr_numbers,
                SUM(administrative_seizure = 1)                                   AS administrative_seizures,
                SUM(criminal_seizure = 1)                                         AS criminal_seizures,
                SUM(weapon_precautionary_withdrawal = 1)                          AS weapon_withdrawals
             FROM controls
             {$whereSql}"
        );
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    // ── Per categoria (con breakdown esiti — spec §15 punti 8-14) ────

    private function byCategory(string $whereSql, array $params): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                activity_categories.id,
                activity_categories.name,
                COUNT(DISTINCT controls.id)                              AS total,
                SUM(controls.outcome = 'positivo')                       AS positive,
                SUM(controls.outcome = 'negativo')                       AS negative,
                SUM(controls.outcome = 'in_accertamento')                AS investigation,
                COALESCE(SUM(controls.total_sanction_amount), 0)         AS sanctions
             FROM controls
             INNER JOIN control_activity_category
                ON control_activity_category.control_id = controls.id
             INNER JOIN activity_categories
                ON activity_categories.id = control_activity_category.activity_category_id
             {$whereSql}
             GROUP BY activity_categories.id, activity_categories.name
             ORDER BY total DESC, activity_categories.name"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    // ── Per agente (spec §15 punto 12) ───────────────────────────────

    private function byAgent(string $whereSql, array $params): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                agents.id,
                CONCAT(agents.surname, ' ', agents.name) AS name,
                agents.rank,
                COUNT(DISTINCT controls.id) AS total
             FROM controls
             INNER JOIN agent_control ON agent_control.control_id = controls.id
             INNER JOIN agents       ON agents.id = agent_control.agent_id
             {$whereSql}
             GROUP BY agents.id, agents.surname, agents.name, agents.rank
             ORDER BY total DESC, agents.surname, agents.name"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    // ── Per evento (spec §15 punti 7, 15) ────────────────────────────

    private function byEvent(string $whereSql, array $params): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                events.id,
                COALESCE(events.name, 'Nessuno') AS name,
                COUNT(controls.id)                               AS total,
                COALESCE(SUM(controls.total_sanction_amount), 0) AS sanctions
             FROM controls
             LEFT JOIN events ON events.id = controls.event_id
             {$whereSql}
             GROUP BY events.id, events.name
             ORDER BY total DESC, name"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    // ── WHERE builder ─────────────────────────────────────────────────

    private function where(array $filters): array
    {
        $where  = [];
        $params = [];

        if (($filters['year'] ?? '') !== '') {
            $where[]         = 'controls.registry_year = :year';
            $params['year']  = (int) $filters['year'];
        }

        if (($filters['month'] ?? '') !== '') {
            $where[]          = 'MONTH(controls.control_date) = :month';
            $params['month']  = (int) $filters['month'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $where[]              = 'controls.control_date >= :date_from';
            $params['date_from']  = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $where[]            = 'controls.control_date <= :date_to';
            $params['date_to']  = $filters['date_to'];
        }

        if (($filters['outcome'] ?? '') !== '') {
            $where[]             = 'controls.outcome = :outcome';
            $params['outcome']   = $filters['outcome'];
        }

        if (($filters['event_id'] ?? '') !== '') {
            $where[]              = 'controls.event_id = :event_id';
            $params['event_id']   = (int) $filters['event_id'];
        }

        if (($filters['category_id'] ?? '') !== '') {
            // Subquery per non duplicare i controlli che hanno più categorie
            $where[] = 'EXISTS (
                SELECT 1 FROM control_activity_category cac_f
                WHERE cac_f.control_id = controls.id
                  AND cac_f.activity_category_id = :category_id
            )';
            $params['category_id'] = (int) $filters['category_id'];
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $params];
    }
}
