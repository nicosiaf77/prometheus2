<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;

final class IntegrityCheckService
{
    public function run(int $userId): array
    {
        $issues = [];
        $controls = Database::connection()->query(
            'SELECT id, hash_record FROM controls ORDER BY id'
        )->fetchAll();

        foreach ($controls as $control) {
            $versions = $this->versions((int) $control['id']);

            if ($versions === []) {
                $issues[] = 'Controllo #' . $control['id'] . ' senza versioni.';
                continue;
            }

            $lastVersion = end($versions);

            if (($lastVersion['hash_version'] ?? '') !== $control['hash_record']) {
                $issues[] = 'Controllo #' . $control['id'] . ' hash corrente diverso dall ultima versione.';
            }

            $previousHash = null;

            foreach ($versions as $version) {
                if (($version['previous_hash'] ?? null) !== $previousHash) {
                    $issues[] = 'Controllo #' . $control['id'] . ' catena hash incoerente alla versione ' . $version['version_number'] . '.';
                }

                $previousHash = $version['hash_version'];
            }
        }

        (new AuditService())->record(
            AuditActions::INTEGRITY_CHECK,
            'controls',
            null,
            $issues === [] ? 'Verifica integrita completata senza errori.' : 'Verifica integrita con anomalie: ' . count($issues)
        );

        return [
            'checked_controls' => count($controls),
            'issues' => $issues,
        ];
    }

    private function versions(int $controlId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT version_number, hash_version, previous_hash
             FROM control_versions
             WHERE control_id = :control_id
             ORDER BY version_number'
        );
        $statement->execute(['control_id' => $controlId]);

        return $statement->fetchAll();
    }
}
