<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\Env;
use Throwable;

final class ControlService
{
    public function latest(int $limit = 50): array
    {
        $statement = Database::connection()->prepare(
            "SELECT controls.id,
                    controls.registry_number,
                    controls.registry_year,
                    controls.control_date,
                    COALESCE(events.name, 'Nessuno') AS event_name,
                    controls.business_name,
                    controls.business_location,
                    controls.outcome,
                    controls.status
             FROM controls
             LEFT JOIN events ON events.id = controls.event_id
             ORDER BY controls.created_at DESC
             LIMIT :limit"
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function create(array $data, int $userId): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $registryYear = (int) substr((string) $data['control_date'], 0, 4);
            $registryNumber = $this->nextRegistryNumber($registryYear);
            $eventId = $this->resolveEventId($data, $userId);
            $encrypted = $this->encryptedFields($data);
            $hashPayload = [
                'registry_number' => $registryNumber,
                'registry_year' => $registryYear,
                'control_date' => $data['control_date'],
                'control_time' => $data['control_time'],
                'business_name' => $data['business_name'],
                'outcome' => $data['outcome'],
                'created_by' => $userId,
            ];
            $hash = (new HashChainService())->calculate($hashPayload);

            $statement = $pdo->prepare(
                'INSERT INTO controls (
                    registry_number, registry_year, control_date, control_time, has_event, event_id,
                    business_name, business_location, business_owner_encrypted, offender_encrypted,
                    outcome, violated_rules, sanctioning_rules, reduced_payment_amount, minimum_amount,
                    maximum_amount, total_sanction_amount, alleged_crime, cnr_number_encrypted,
                    administrative_seizure, administrative_seizure_description, criminal_seizure,
                    criminal_seizure_description, weapon_precautionary_withdrawal,
                    weapon_precautionary_withdrawal_description, notes_encrypted, status,
                    hash_record, previous_hash, created_by, created_at, updated_at
                ) VALUES (
                    :registry_number, :registry_year, :control_date, :control_time, :has_event, :event_id,
                    :business_name, :business_location, :business_owner_encrypted, :offender_encrypted,
                    :outcome, :violated_rules, :sanctioning_rules, :reduced_payment_amount, :minimum_amount,
                    :maximum_amount, :total_sanction_amount, :alleged_crime, :cnr_number_encrypted,
                    :administrative_seizure, :administrative_seizure_description, :criminal_seizure,
                    :criminal_seizure_description, :weapon_precautionary_withdrawal,
                    :weapon_precautionary_withdrawal_description, :notes_encrypted, :status,
                    :hash_record, NULL, :created_by, NOW(), NOW()
                )'
            );
            $statement->execute([
                'registry_number' => $registryNumber,
                'registry_year' => $registryYear,
                'control_date' => $data['control_date'],
                'control_time' => $data['control_time'],
                'has_event' => (int) $data['has_event'],
                'event_id' => $eventId,
                'business_name' => $data['business_name'],
                'business_location' => $data['business_location'],
                'business_owner_encrypted' => $encrypted['business_owner'],
                'offender_encrypted' => $encrypted['offender'],
                'outcome' => $data['outcome'],
                'violated_rules' => $data['violated_rules'] !== '' ? $data['violated_rules'] : null,
                'sanctioning_rules' => $data['sanctioning_rules'] !== '' ? $data['sanctioning_rules'] : null,
                'reduced_payment_amount' => $this->decimalOrNull($data['reduced_payment_amount']),
                'minimum_amount' => $this->decimalOrNull($data['minimum_amount']),
                'maximum_amount' => $this->decimalOrNull($data['maximum_amount']),
                'total_sanction_amount' => $this->decimalOrNull($data['total_sanction_amount']),
                'alleged_crime' => $data['alleged_crime'] !== '' ? $data['alleged_crime'] : null,
                'cnr_number_encrypted' => $encrypted['cnr_number'],
                'administrative_seizure' => (int) $data['administrative_seizure'],
                'administrative_seizure_description' => $data['administrative_seizure_description'] !== '' ? $data['administrative_seizure_description'] : null,
                'criminal_seizure' => (int) $data['criminal_seizure'],
                'criminal_seizure_description' => $data['criminal_seizure_description'] !== '' ? $data['criminal_seizure_description'] : null,
                'weapon_precautionary_withdrawal' => (int) $data['weapon_precautionary_withdrawal'],
                'weapon_precautionary_withdrawal_description' => $data['weapon_precautionary_withdrawal_description'] !== '' ? $data['weapon_precautionary_withdrawal_description'] : null,
                'notes_encrypted' => $encrypted['notes'],
                'status' => 'bozza',
                'hash_record' => $hash,
                'created_by' => $userId,
            ]);

            $controlId = (int) $pdo->lastInsertId();
            $this->syncCategories($controlId, (int) $data['primary_category_id'], $data['secondary_category_ids']);
            $this->syncAgents($controlId, $data['agent_ids']);
            $this->createInitialVersion($controlId, $data, $hash, $userId);

            (new AuditService())->record(AuditActions::CONTROL_CREATED, 'controls', $controlId, 'Controllo creato');
            $pdo->commit();

            return $controlId;
        } catch (Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }

    private function nextRegistryNumber(int $year): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(MAX(registry_number), 0) + 1 FROM controls WHERE registry_year = :year FOR UPDATE'
        );
        $statement->execute(['year' => $year]);

        return (int) $statement->fetchColumn();
    }

    private function resolveEventId(array $data, int $userId): ?int
    {
        if ((int) $data['has_event'] !== 1 || trim((string) $data['event_name']) === '') {
            return null;
        }

        $eventService = new EventService();
        $eventService->create((string) $data['event_name'], $userId);
        $event = $eventService->findByName((string) $data['event_name']);

        return is_array($event) ? (int) $event['id'] : null;
    }

    private function encryptedFields(array $data): array
    {
        $service = new EncryptionService();
        $key = Env::get('APP_KEY', 'change-me') ?? 'change-me';

        return [
            'business_owner' => $this->encryptNullable($service, (string) $data['business_owner'], $key),
            'offender' => $this->encryptNullable($service, (string) $data['offender'], $key),
            'cnr_number' => $this->encryptNullable($service, (string) $data['cnr_number'], $key),
            'notes' => $this->encryptNullable($service, (string) $data['notes'], $key),
        ];
    }

    private function encryptNullable(EncryptionService $service, string $value, string $key): ?string
    {
        $value = trim($value);

        return $value !== '' ? $service->encrypt($value, $key) : null;
    }

    private function decimalOrNull(string $value): ?string
    {
        $normalized = str_replace(',', '.', trim($value));

        return $normalized !== '' && is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : null;
    }

    private function syncCategories(int $controlId, int $primaryCategoryId, array $secondaryCategoryIds): void
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO control_activity_category (control_id, activity_category_id, is_primary, created_at, updated_at)
             VALUES (:control_id, :activity_category_id, :is_primary, NOW(), NOW())'
        );
        $statement->execute([
            'control_id' => $controlId,
            'activity_category_id' => $primaryCategoryId,
            'is_primary' => 1,
        ]);

        foreach (array_unique(array_map('intval', $secondaryCategoryIds)) as $categoryId) {
            if ($categoryId <= 0 || $categoryId === $primaryCategoryId) {
                continue;
            }

            $statement->execute([
                'control_id' => $controlId,
                'activity_category_id' => $categoryId,
                'is_primary' => 0,
            ]);
        }
    }

    private function syncAgents(int $controlId, array $agentIds): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO agent_control (control_id, agent_id, role_in_control, created_at, updated_at)
             VALUES (:control_id, :agent_id, NULL, NOW(), NOW())'
        );

        foreach (array_unique(array_map('intval', $agentIds)) as $agentId) {
            if ($agentId <= 0) {
                continue;
            }

            $statement->execute([
                'control_id' => $controlId,
                'agent_id' => $agentId,
            ]);
        }
    }

    private function createInitialVersion(int $controlId, array $data, string $hash, int $userId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO control_versions (control_id, version_number, data_json, hash_version, previous_hash, changed_by, change_reason, created_at)
             VALUES (:control_id, 1, :data_json, :hash_version, NULL, :changed_by, :change_reason, NOW())'
        );
        $statement->execute([
            'control_id' => $controlId,
            'data_json' => json_encode($data, JSON_THROW_ON_ERROR),
            'hash_version' => $hash,
            'changed_by' => $userId,
            'change_reason' => 'Creazione controllo',
        ]);
    }
}
