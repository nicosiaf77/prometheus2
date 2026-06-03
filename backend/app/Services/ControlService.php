<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\Env;
use PDO;
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

    public function search(array $filters, int $limit = 100): array
    {
        return $this->paginate($filters, 1, $limit)['data'];
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 25, string $sort = 'control_date', string $direction = 'desc'): array
    {
        $joins = [
            'LEFT JOIN events ON events.id = controls.event_id',
        ];
        $where = [];
        $params = [];

        if (($filters['registry_number'] ?? '') !== '') {
            $where[] = 'controls.registry_number = :registry_number';
            $params['registry_number'] = (int) $filters['registry_number'];
        }

        if (($filters['registry_year'] ?? '') !== '') {
            $where[] = 'controls.registry_year = :registry_year';
            $params['registry_year'] = (int) $filters['registry_year'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $where[] = 'controls.control_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $where[] = 'controls.control_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (($filters['has_event'] ?? '') !== '') {
            $where[] = 'controls.has_event = :has_event';
            $params['has_event'] = (int) $filters['has_event'];
        }

        if (($filters['event_name'] ?? '') !== '') {
            $where[] = 'events.name LIKE :event_name';
            $params['event_name'] = '%' . $filters['event_name'] . '%';
        }

        if (($filters['business_name'] ?? '') !== '') {
            $where[] = 'controls.business_name LIKE :business_name';
            $params['business_name'] = '%' . $filters['business_name'] . '%';
        }

        if (($filters['business_location'] ?? '') !== '') {
            $where[] = 'controls.business_location LIKE :business_location';
            $params['business_location'] = '%' . $filters['business_location'] . '%';
        }

        if (($filters['outcome'] ?? '') !== '') {
            $where[] = 'controls.outcome = :outcome';
            $params['outcome'] = $filters['outcome'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'controls.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['sanction_presence'] ?? '') === '1') {
            $where[] = 'controls.total_sanction_amount IS NOT NULL AND controls.total_sanction_amount > 0';
        }

        if (($filters['category_id'] ?? '') !== '') {
            $joins[] = 'INNER JOIN control_activity_category filter_categories ON filter_categories.control_id = controls.id';
            $where[] = 'filter_categories.activity_category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['agent_id'] ?? '') !== '') {
            $joins[] = 'INNER JOIN agent_control filter_agents ON filter_agents.control_id = controls.id';
            $where[] = 'filter_agents.agent_id = :agent_id';
            $params['agent_id'] = (int) $filters['agent_id'];
        }

        $sortColumns = [
            'registry_number' => 'controls.registry_number',
            'registry_year' => 'controls.registry_year',
            'control_date' => 'controls.control_date',
            'business_name' => 'controls.business_name',
            'business_location' => 'controls.business_location',
            'outcome' => 'controls.outcome',
            'status' => 'controls.status',
            'total_sanction_amount' => 'controls.total_sanction_amount',
            'created_at' => 'controls.created_at',
        ];
        $sortColumn = $sortColumns[$sort] ?? $sortColumns['control_date'];
        $sortDirection = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $fromSql = ' FROM controls ' . implode(' ', $joins);
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $countStatement = Database::connection()->prepare('SELECT COUNT(DISTINCT controls.id)' . $fromSql . $whereSql);

        foreach ($params as $key => $value) {
            $countStatement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();
        $sql = "SELECT DISTINCT controls.id,
                    controls.registry_number,
                    controls.registry_year,
                    controls.control_date,
                    COALESCE(events.name, 'Nessuno') AS event_name,
                    controls.business_name,
                    controls.business_location,
                    controls.outcome,
                    controls.status,
                    controls.total_sanction_amount
             {$fromSql}
             {$whereSql}
             ORDER BY {$sortColumn} {$sortDirection}, controls.registry_number {$sortDirection}
             LIMIT :limit OFFSET :offset";
        $statement = Database::connection()->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'data' => $statement->fetchAll(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'sort' => array_key_exists($sort, $sortColumns) ? $sort : 'control_date',
                'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc',
            ],
        ];
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT controls.*,
                    COALESCE(events.name, 'Nessuno') AS event_name,
                    creator.username AS created_by_username,
                    validator.username AS validated_by_username,
                    annuller.username AS annulled_by_username
             FROM controls
             LEFT JOIN events ON events.id = controls.event_id
             LEFT JOIN users creator ON creator.id = controls.created_by
             LEFT JOIN users validator ON validator.id = controls.validated_by
             LEFT JOIN users annuller ON annuller.id = controls.annulled_by
             WHERE controls.id = :id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $control = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($control)) {
            return null;
        }

        $control['business_owner'] = $this->decryptNullable($control['business_owner_encrypted']);
        $control['offender'] = $this->decryptNullable($control['offender_encrypted']);
        $control['cnr_number'] = $this->decryptNullable($control['cnr_number_encrypted']);
        $control['notes'] = $this->decryptNullable($control['notes_encrypted']);

        $allCategories = $this->categories($id);
        $control['primary_category'] = null;
        $control['secondary_categories'] = [];

        foreach ($allCategories as $cat) {
            if ((bool) $cat['is_primary']) {
                $control['primary_category'] = $cat;
            } else {
                $control['secondary_categories'][] = $cat;
            }
        }

        $control['agents'] = $this->agents($id);
        $control['versions'] = $this->versions($id);

        return $control;
    }

    public function versionsList(int $controlId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id FROM controls WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $controlId]);

        if ($statement->fetchColumn() === false) {
            return null;
        }

        return $this->versions($controlId);
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

    public function update(int $controlId, array $data, int $userId, string $changeReason): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $control = $this->lockControl($controlId);

            if ($control === null) {
                throw new \RuntimeException('Controllo non trovato.');
            }

            if ($control['status'] === 'annullato') {
                throw new \RuntimeException('Un controllo annullato non può essere modificato.');
            }

            $registryYear = (int) substr((string) $data['control_date'], 0, 4);
            $eventId = $this->resolveEventId($data, $userId);
            $encrypted = $this->encryptedFields($data);
            $previousHash = (string) $control['hash_record'];
            $hashPayload = [
                'registry_number' => $control['registry_number'],
                'registry_year' => $registryYear,
                'control_date' => $data['control_date'],
                'control_time' => $data['control_time'],
                'business_name' => $data['business_name'],
                'outcome' => $data['outcome'],
                'updated_by' => $userId,
                'change_reason' => $changeReason,
            ];
            $newHash = (new HashChainService())->calculate($hashPayload, $previousHash);

            $pdo->prepare(
                'UPDATE controls
                 SET control_date = :control_date,
                     control_time = :control_time,
                     registry_year = :registry_year,
                     has_event = :has_event,
                     event_id = :event_id,
                     business_name = :business_name,
                     business_location = :business_location,
                     business_owner_encrypted = :business_owner_encrypted,
                     offender_encrypted = :offender_encrypted,
                     outcome = :outcome,
                     violated_rules = :violated_rules,
                     sanctioning_rules = :sanctioning_rules,
                     reduced_payment_amount = :reduced_payment_amount,
                     minimum_amount = :minimum_amount,
                     maximum_amount = :maximum_amount,
                     total_sanction_amount = :total_sanction_amount,
                     alleged_crime = :alleged_crime,
                     cnr_number_encrypted = :cnr_number_encrypted,
                     administrative_seizure = :administrative_seizure,
                     administrative_seizure_description = :administrative_seizure_description,
                     criminal_seizure = :criminal_seizure,
                     criminal_seizure_description = :criminal_seizure_description,
                     weapon_precautionary_withdrawal = :weapon_precautionary_withdrawal,
                     weapon_precautionary_withdrawal_description = :weapon_precautionary_withdrawal_description,
                     notes_encrypted = :notes_encrypted,
                     hash_record = :hash_record,
                     previous_hash = :previous_hash,
                     updated_by = :updated_by,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'control_date' => $data['control_date'],
                'control_time' => $data['control_time'],
                'registry_year' => $registryYear,
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
                'hash_record' => $newHash,
                'previous_hash' => $previousHash,
                'updated_by' => $userId,
                'id' => $controlId,
            ]);

            $this->syncCategories($controlId, (int) $data['primary_category_id'], $data['secondary_category_ids'], true);
            $this->syncAgents($controlId, $data['agent_ids'], true);

            $updatedControl = $this->lockControl($controlId);
            $this->createVersionFromSnapshot(
                $controlId,
                array_merge((array) $updatedControl, ['change_data' => $data]),
                $newHash,
                $previousHash,
                $userId,
                $changeReason
            );

            (new AuditService())->record(AuditActions::CONTROL_UPDATED, 'controls', $controlId, 'Controllo modificato: ' . $changeReason);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }

    public function validate(int $controlId, int $userId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $control = $this->lockControl($controlId);

            if ($control === null) {
                throw new \RuntimeException('Controllo non trovato.');
            }

            if ($control['status'] === 'annullato') {
                throw new \RuntimeException('Un controllo annullato non puo essere validato.');
            }

            if ($control['status'] === 'validato') {
                throw new \RuntimeException('Il controllo risulta gia validato.');
            }

            $previousHash = (string) $control['hash_record'];
            $snapshot = array_merge($control, [
                'status' => 'validato',
                'validated_by' => $userId,
                'validated_at' => date('Y-m-d H:i:s'),
            ]);
            $newHash = (new HashChainService())->calculate($snapshot, $previousHash);

            $pdo->prepare(
                'UPDATE controls
                 SET status = :status,
                     validated_by = :validated_by,
                     validated_at = NOW(),
                     updated_by = :updated_by,
                     hash_record = :hash_record,
                     previous_hash = :previous_hash,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'status' => 'validato',
                'validated_by' => $userId,
                'updated_by' => $userId,
                'hash_record' => $newHash,
                'previous_hash' => $previousHash,
                'id' => $controlId,
            ]);

            $this->createVersionFromSnapshot($controlId, $snapshot, $newHash, $previousHash, $userId, 'Validazione controllo');
            (new AuditService())->record(AuditActions::CONTROL_VALIDATED, 'controls', $controlId, 'Controllo validato');
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }

    public function annul(int $controlId, int $userId, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Il motivo dell annullamento e obbligatorio.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $control = $this->lockControl($controlId);

            if ($control === null) {
                throw new \RuntimeException('Controllo non trovato.');
            }

            if ($control['status'] === 'annullato') {
                throw new \RuntimeException('Il controllo risulta gia annullato.');
            }

            $previousHash = (string) $control['hash_record'];
            $snapshot = array_merge($control, [
                'status' => 'annullato',
                'annulled_by' => $userId,
                'annulled_at' => date('Y-m-d H:i:s'),
                'annulment_reason' => $reason,
            ]);
            $newHash = (new HashChainService())->calculate($snapshot, $previousHash);

            $pdo->prepare(
                'UPDATE controls
                 SET status = :status,
                     annulled_by = :annulled_by,
                     annulled_at = NOW(),
                     annulment_reason = :annulment_reason,
                     updated_by = :updated_by,
                     hash_record = :hash_record,
                     previous_hash = :previous_hash,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'status' => 'annullato',
                'annulled_by' => $userId,
                'annulment_reason' => $reason,
                'updated_by' => $userId,
                'hash_record' => $newHash,
                'previous_hash' => $previousHash,
                'id' => $controlId,
            ]);

            $this->createVersionFromSnapshot($controlId, $snapshot, $newHash, $previousHash, $userId, 'Annullamento controllo: ' . $reason);
            (new AuditService())->record(AuditActions::CONTROL_ANNULLED, 'controls', $controlId, 'Controllo annullato: ' . $reason);
            $pdo->commit();
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

    private function decryptNullable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return (new EncryptionService())->decrypt($value, Env::get('APP_KEY', 'change-me') ?? 'change-me');
        } catch (Throwable) {
            return '[dato non decifrabile]';
        }
    }

    private function decimalOrNull(string $value): ?string
    {
        $normalized = str_replace(',', '.', trim($value));

        return $normalized !== '' && is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : null;
    }

    private function syncCategories(int $controlId, int $primaryCategoryId, array $secondaryCategoryIds, bool $replace = false): void
    {
        $pdo = Database::connection();

        if ($replace) {
            $pdo->prepare('DELETE FROM control_activity_category WHERE control_id = :control_id')
                ->execute(['control_id' => $controlId]);
        }

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

    private function syncAgents(int $controlId, array $agentIds, bool $replace = false): void
    {
        $pdo = Database::connection();

        if ($replace) {
            $pdo->prepare('DELETE FROM agent_control WHERE control_id = :control_id')
                ->execute(['control_id' => $controlId]);
        }

        $statement = $pdo->prepare(
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

    private function lockControl(int $controlId): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM controls WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $controlId]);
        $control = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($control) ? $control : null;
    }

    private function categories(int $controlId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT activity_categories.name, control_activity_category.is_primary
             FROM control_activity_category
             INNER JOIN activity_categories ON activity_categories.id = control_activity_category.activity_category_id
             WHERE control_activity_category.control_id = :control_id
             ORDER BY control_activity_category.is_primary DESC, activity_categories.name'
        );
        $statement->execute(['control_id' => $controlId]);

        return $statement->fetchAll();
    }

    private function agents(int $controlId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT agents.name, agents.surname, agents.rank, agents.office
             FROM agent_control
             INNER JOIN agents ON agents.id = agent_control.agent_id
             WHERE agent_control.control_id = :control_id
             ORDER BY agents.surname, agents.name'
        );
        $statement->execute(['control_id' => $controlId]);

        return $statement->fetchAll();
    }

    private function versions(int $controlId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT control_versions.version_number,
                    control_versions.hash_version,
                    control_versions.previous_hash,
                    control_versions.change_reason,
                    control_versions.created_at,
                    users.username AS changed_by_username
             FROM control_versions
             LEFT JOIN users ON users.id = control_versions.changed_by
             WHERE control_versions.control_id = :control_id
             ORDER BY control_versions.version_number DESC'
        );
        $statement->execute(['control_id' => $controlId]);

        return $statement->fetchAll();
    }

    private function createVersionFromSnapshot(
        int $controlId,
        array $snapshot,
        string $hash,
        string $previousHash,
        int $userId,
        string $reason
    ): void {
        $versionStatement = Database::connection()->prepare(
            'SELECT COALESCE(MAX(version_number), 0) + 1 FROM control_versions WHERE control_id = :control_id FOR UPDATE'
        );
        $versionStatement->execute(['control_id' => $controlId]);
        $versionNumber = (int) $versionStatement->fetchColumn();

        $insertStatement = Database::connection()->prepare(
            'INSERT INTO control_versions (control_id, version_number, data_json, hash_version, previous_hash, changed_by, change_reason, created_at)
             VALUES (:control_id, :version_number, :data_json, :hash_version, :previous_hash, :changed_by, :change_reason, NOW())'
        );
        $insertStatement->execute([
            'control_id' => $controlId,
            'version_number' => $versionNumber,
            'data_json' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'hash_version' => $hash,
            'previous_hash' => $previousHash,
            'changed_by' => $userId,
            'change_reason' => $reason,
        ]);
    }
}
