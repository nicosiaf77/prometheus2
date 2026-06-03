<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Core\Validator;
use Prometheus\Requests\StoreControlRequest;
use Prometheus\Requests\UpdateControlRequest;
use Prometheus\Services\ActivityCategoryService;
use Prometheus\Services\AgentService;
use Prometheus\Services\AuditActions;
use Prometheus\Services\AuditService;
use Prometheus\Services\ControlService;
use Prometheus\Services\EventService;
use Throwable;

final class ControlController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        $request = new Request();
        $filters = $this->searchFilters($request);
        $page = $this->positiveInteger($request->input('page', '1') ?? '1', 1);
        $perPage = $this->positiveInteger($request->input('per_page', '25') ?? '25', 25);
        $sort = $request->input('sort', 'control_date') ?? 'control_date';
        $direction = $request->input('direction', 'desc') ?? 'desc';
        $result = (new ControlService())->paginate($filters, $page, $perPage, $sort, $direction);

        return $this->json([
            'ok' => true,
            'filters' => $filters,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function create(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'message' => 'Metadati backend per creare un controllo tramite POST /controls.',
            'defaults' => [
                'control_date' => date('Y-m-d'),
                'control_time' => date('H:i'),
                'has_event' => 0,
                'outcome' => 'positivo',
                'administrative_seizure' => 0,
                'criminal_seizure' => 0,
                'weapon_precautionary_withdrawal' => 0,
            ],
            'accepted_outcomes' => ['positivo', 'negativo', 'in_accertamento'],
            'categories' => (new ActivityCategoryService())->active(),
            'agents' => (new AgentService())->all(),
            'events' => (new EventService())->all(),
            'required_fields' => [
                'control_date',
                'control_time',
                'business_name',
                'business_location',
                'primary_category_id',
                'outcome',
            ],
        ]);
    }

    public function show(string $control): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        if (!ctype_digit($control)) {
            return $this->error('Controllo non trovato.', 404);
        }

        $controlData = (new ControlService())->find((int) $control);

        if ($controlData === null) {
            return $this->error('Controllo non trovato.', 404);
        }

        (new AuditService())->record(AuditActions::CONTROL_VIEWED, 'controls', (int) $control, 'Accesso dettaglio controllo');

        return $this->json([
            'ok' => true,
            'control' => $controlData,
            'available_actions' => $this->availableActions($controlData),
        ]);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();
        $data = $this->controlData($request);
        $errors = (new Validator())->validate($data, (new StoreControlRequest())->rules());
        $errors = $this->withControlConditionalErrors($data, $errors);

        if ($user === null) {
            return $this->error('Utente non valido.', 422);
        }

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        try {
            $controlId = (new ControlService())->create($data, (int) $user['id']);
        } catch (Throwable $exception) {
            return $this->error('Impossibile salvare il controllo: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Controllo salvato in bozza.',
            'control_id' => $controlId,
        ], 201);
    }

    public function update(string $control): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($control)) {
            return $this->error('Controllo non trovato.', 404);
        }

        $user = $this->auth()->user();

        if ($user === null) {
            return $this->error('Utente non valido.', 422);
        }

        $data = $this->controlData($request);
        $changeReason = trim($request->input('change_reason', '') ?? '');
        $data['change_reason'] = $changeReason;
        $errors = (new Validator())->validate($data, (new UpdateControlRequest())->rules());
        $errors = $this->withControlConditionalErrors($data, $errors);

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $existing = (new ControlService())->find((int) $control);

        if ($existing === null) {
            return $this->error('Controllo non trovato.', 404);
        }

        if ($existing['status'] === 'annullato') {
            return $this->error('Un controllo annullato non può essere modificato.', 422);
        }

        if ($existing['status'] === 'validato' && !$this->auth()->hasRole(['amministratore', 'responsabile_ufficio'])) {
            return $this->error('Solo amministratore o responsabile ufficio può modificare un controllo validato.', 403);
        }

        try {
            (new ControlService())->update((int) $control, $data, (int) $user['id'], $changeReason);
        } catch (Throwable $exception) {
            return $this->error('Modifica non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Controllo aggiornato correttamente.',
            'control_id' => (int) $control,
        ]);
    }

    public function validate(string $control): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($control)) {
            return $this->error('Controllo non trovato.', 404);
        }

        $user = $this->auth()->user();

        try {
            (new ControlService())->validate((int) $control, (int) $user['id']);
        } catch (Throwable $exception) {
            return $this->error('Validazione non riuscita: ' . $exception->getMessage(), 422);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Controllo validato correttamente.',
            'control_id' => (int) $control,
        ]);
    }

    public function annul(string $control): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($control)) {
            return $this->error('Controllo non trovato.', 404);
        }

        $reason = $request->input('annulment_reason', '') ?? '';

        if ($reason === '') {
            return $this->error('Motivo annullamento obbligatorio.', 422);
        }

        $user = $this->auth()->user();

        try {
            (new ControlService())->annul((int) $control, (int) $user['id'], $reason);
        } catch (Throwable $exception) {
            return $this->error('Annullamento non riuscito: ' . $exception->getMessage(), 422);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Controllo annullato logicamente.',
            'control_id' => (int) $control,
        ]);
    }

    private function controlData(Request $request): array
    {
        return [
            'control_date' => $request->input('control_date', '') ?? '',
            'control_time' => $request->input('control_time', '') ?? '',
            'has_event' => $request->boolean('has_event') ? 1 : 0,
            'event_name' => $request->input('event_name', '') ?? '',
            'business_name' => $request->input('business_name', '') ?? '',
            'business_location' => $request->input('business_location', '') ?? '',
            'business_owner' => $request->input('business_owner', '') ?? '',
            'offender' => $request->input('offender', '') ?? '',
            'primary_category_id' => $request->input('primary_category_id', '') ?? '',
            'secondary_category_ids' => $request->array('secondary_category_ids'),
            'agent_ids' => $request->array('agent_ids'),
            'outcome' => $request->input('outcome', '') ?? '',
            'violated_rules' => $request->input('violated_rules', '') ?? '',
            'sanctioning_rules' => $request->input('sanctioning_rules', '') ?? '',
            'reduced_payment_amount' => $request->input('reduced_payment_amount', '') ?? '',
            'minimum_amount' => $request->input('minimum_amount', '') ?? '',
            'maximum_amount' => $request->input('maximum_amount', '') ?? '',
            'total_sanction_amount' => $request->input('total_sanction_amount', '') ?? '',
            'alleged_crime' => $request->input('alleged_crime', '') ?? '',
            'cnr_number' => $request->input('cnr_number', '') ?? '',
            'administrative_seizure' => $request->boolean('administrative_seizure') ? 1 : 0,
            'administrative_seizure_description' => $request->input('administrative_seizure_description', '') ?? '',
            'criminal_seizure' => $request->boolean('criminal_seizure') ? 1 : 0,
            'criminal_seizure_description' => $request->input('criminal_seizure_description', '') ?? '',
            'weapon_precautionary_withdrawal' => $request->boolean('weapon_precautionary_withdrawal') ? 1 : 0,
            'weapon_precautionary_withdrawal_description' => $request->input('weapon_precautionary_withdrawal_description', '') ?? '',
            'notes' => $request->input('notes', '') ?? '',
        ];
    }

    private function withControlConditionalErrors(array $data, array $errors): array
    {
        if ((int) $data['has_event'] === 1 && trim((string) $data['event_name']) === '') {
            $errors['event_name'][] = 'Il nome evento e obbligatorio quando il controllo e collegato a evento.';
        }

        return $errors;
    }

    private function searchFilters(Request $request): array
    {
        return [
            'registry_number' => $request->input('registry_number', '') ?? '',
            'registry_year' => $request->input('registry_year', '') ?? '',
            'date_from' => $request->input('date_from', '') ?? '',
            'date_to' => $request->input('date_to', '') ?? '',
            'has_event' => $request->input('has_event', '') ?? '',
            'event_name' => $request->input('event_name', '') ?? '',
            'business_name' => $request->input('business_name', '') ?? '',
            'business_location' => $request->input('business_location', '') ?? '',
            'category_id' => $request->input('category_id', '') ?? '',
            'agent_id' => $request->input('agent_id', '') ?? '',
            'outcome' => $request->input('outcome', '') ?? '',
            'status' => $request->input('status', '') ?? '',
            'sanction_presence' => $request->input('sanction_presence', '') ?? '',
        ];
    }

    private function positiveInteger(string $value, int $default): int
    {
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private function availableActions(array $control): array
    {
        if (!$this->auth()->hasRole(['amministratore', 'responsabile_ufficio']) || $control['status'] === 'annullato') {
            return [];
        }

        $actions = [
            [
                'name' => 'annul',
                'method' => 'POST',
                'endpoint' => '/controls/' . $control['id'] . '/annul',
                'required_fields' => ['_csrf_token', 'annulment_reason'],
            ],
        ];

        if ($control['status'] === 'bozza') {
            array_unshift($actions, [
                'name' => 'validate',
                'method' => 'POST',
                'endpoint' => '/controls/' . $control['id'] . '/validate',
                'required_fields' => ['_csrf_token'],
            ]);
        }

        return $actions;
    }
}
