<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
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
        $categories = (new ActivityCategoryService())->active();
        $agents = (new AgentService())->all();
        $events = (new EventService())->all();
        $controls = (new ControlService())->search($filters);
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-success py-2">' . $this->e($flash) . '</div>' : '';
        $categoryOptions = $this->optionsWithSelected($categories, 'id', 'name', $filters['category_id']);
        $agentOptions = $this->agentOptionsWithSelected($agents, $filters['agent_id']);
        $eventOptions = $this->options($events, 'name', 'name');
        $rows = '';

        foreach ($controls as $control) {
            $registry = $this->e($control['registry_number'] . '/' . $control['registry_year']);
            $rows .= '<tr>'
                . '<td>' . $registry . '</td>'
                . '<td>' . $this->e($control['control_date']) . '</td>'
                . '<td>' . $this->e($control['event_name']) . '</td>'
                . '<td>' . $this->e($control['business_name']) . '</td>'
                . '<td>' . $this->e($control['business_location']) . '</td>'
                . '<td>' . $this->e($control['outcome']) . '</td>'
                . '<td>' . $this->e($control['status']) . '</td>'
                . '<td>EUR ' . $this->e($control['total_sanction_amount'] ?? '0.00') . '</td>'
                . '<td><a class="btn btn-sm btn-outline-secondary" href="/controls/' . $this->e($control['id']) . '">Apri</a></td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9" class="text-muted">Nessun controllo trovato.</td></tr>';
        }

        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Ricerca controlli</h1>
                    <p>Filtri operativi sui controlli inseriti.</p>
                </div>
                <a class="btn btn-sm btn-primary" href="/controls/create">Nuovo controllo</a>
            </div>
            {$flashHtml}
            <div class="panel mb-3">
                <form method="get" action="/controls" class="filter-form">
                    <input class="form-control form-control-sm" name="registry_number" value="{$this->e($filters['registry_number'])}" placeholder="Numero">
                    <input class="form-control form-control-sm" name="registry_year" value="{$this->e($filters['registry_year'])}" placeholder="Anno">
                    <input class="form-control form-control-sm" type="date" name="date_from" value="{$this->e($filters['date_from'])}">
                    <input class="form-control form-control-sm" type="date" name="date_to" value="{$this->e($filters['date_to'])}">
                    <select class="form-select form-select-sm" name="has_event">
                        {$this->selectOption('', 'Evento/sfuso', $filters['has_event'])}
                        {$this->selectOption('0', 'Senza evento', $filters['has_event'])}
                        {$this->selectOption('1', 'Con evento', $filters['has_event'])}
                    </select>
                    <input class="form-control form-control-sm" name="event_name" list="event_names" value="{$this->e($filters['event_name'])}" placeholder="Evento">
                    <datalist id="event_names">{$eventOptions}</datalist>
                    <input class="form-control form-control-sm" name="business_name" value="{$this->e($filters['business_name'])}" placeholder="Attivita">
                    <input class="form-control form-control-sm" name="business_location" value="{$this->e($filters['business_location'])}" placeholder="Luogo">
                    <select class="form-select form-select-sm" name="category_id">
                        <option value="">Categoria</option>
                        {$categoryOptions}
                    </select>
                    <select class="form-select form-select-sm" name="agent_id">
                        <option value="">Agente</option>
                        {$agentOptions}
                    </select>
                    <select class="form-select form-select-sm" name="outcome">
                        {$this->selectOption('', 'Esito', $filters['outcome'])}
                        {$this->selectOption('positivo', 'Positivo', $filters['outcome'])}
                        {$this->selectOption('negativo', 'Negativo', $filters['outcome'])}
                        {$this->selectOption('in_accertamento', 'In accertamento', $filters['outcome'])}
                    </select>
                    <select class="form-select form-select-sm" name="status">
                        {$this->selectOption('', 'Stato', $filters['status'])}
                        {$this->selectOption('bozza', 'Bozza', $filters['status'])}
                        {$this->selectOption('validato', 'Validato', $filters['status'])}
                        {$this->selectOption('annullato', 'Annullato', $filters['status'])}
                    </select>
                    <select class="form-select form-select-sm" name="sanction_presence">
                        {$this->selectOption('', 'Sanzione', $filters['sanction_presence'])}
                        {$this->selectOption('1', 'Con sanzione', $filters['sanction_presence'])}
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Filtra</button>
                    <a class="btn btn-sm btn-outline-secondary" href="/controls">Reset</a>
                </form>
            </div>
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Registro</th><th>Data</th><th>Evento</th><th>Attivita</th><th>Luogo</th><th>Esito</th><th>Stato</th><th>Sanzione</th><th>Azioni</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Ricerca controlli', $content);
    }

    public function create(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        $categories = (new ActivityCategoryService())->active();
        $agents = (new AgentService())->all();
        $events = (new EventService())->all();
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-warning py-2">' . $this->e($flash) . '</div>' : '';
        $categoryOptions = $this->options($categories, 'id', 'name');
        $agentOptions = $this->agentOptions($agents);
        $eventOptions = $this->options($events, 'name', 'name');
        $csrf = $this->csrfField();
        $today = date('Y-m-d');
        $time = date('H:i');
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Nuovo controllo</h1>
                    <p>Inserimento controllo amministrativo in stato bozza.</p>
                </div>
            </div>
            {$flashHtml}
            <form method="post" action="/controls" class="control-form">
                {$csrf}
                <section class="panel">
                    <h2>Dati generali</h2>
                    <div class="form-grid">
                        <label>Data controllo<input class="form-control form-control-sm" type="date" name="control_date" value="{$today}" required></label>
                        <label>Ora controllo<input class="form-control form-control-sm" type="time" name="control_time" value="{$time}" required></label>
                        <label>Esito
                            <select class="form-select form-select-sm" name="outcome" required>
                                <option value="positivo">Positivo</option>
                                <option value="negativo">Negativo</option>
                                <option value="in_accertamento">In fase di accertamenti</option>
                            </select>
                        </label>
                    </div>
                </section>
                <section class="panel">
                    <h2>Evento/servizio speciale</h2>
                    <div class="form-grid">
                        <label>Collegato a evento?
                            <select class="form-select form-select-sm" name="has_event" data-has-event>
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </select>
                        </label>
                        <label data-event-name>Nome evento
                            <input class="form-control form-control-sm" name="event_name" list="event_names" placeholder="TASK FORCE, ONE DAY...">
                            <datalist id="event_names">{$eventOptions}</datalist>
                        </label>
                    </div>
                </section>
                <section class="panel">
                    <h2>Attivita controllata</h2>
                    <div class="form-grid">
                        <label>Nome attivita<input class="form-control form-control-sm" name="business_name" required></label>
                        <label>Luogo attivita<input class="form-control form-control-sm" name="business_location" required></label>
                        <label>Titolare attivita<input class="form-control form-control-sm" name="business_owner"></label>
                        <label>Trasgressore<input class="form-control form-control-sm" name="offender"></label>
                    </div>
                </section>
                <section class="panel">
                    <h2>Categorie e agenti</h2>
                    <div class="form-grid">
                        <label>Categoria principale
                            <select class="form-select form-select-sm" name="primary_category_id" required>
                                <option value="">Seleziona</option>
                                {$categoryOptions}
                            </select>
                        </label>
                        <label>Categorie secondarie
                            <select class="form-select form-select-sm" name="secondary_category_ids[]" multiple size="5">
                                {$categoryOptions}
                            </select>
                        </label>
                        <label>Agenti operanti
                            <select class="form-select form-select-sm" name="agent_ids[]" multiple size="5">
                                {$agentOptions}
                            </select>
                        </label>
                    </div>
                </section>
                <section class="panel">
                    <h2>Violazioni e sanzioni</h2>
                    <div class="form-grid">
                        <label>Norme violate<textarea class="form-control form-control-sm" name="violated_rules"></textarea></label>
                        <label>Norme sanzionatrici<textarea class="form-control form-control-sm" name="sanctioning_rules"></textarea></label>
                        <label>Pagamento ridotto<input class="form-control form-control-sm" name="reduced_payment_amount" inputmode="decimal"></label>
                        <label>Importo minimo<input class="form-control form-control-sm" name="minimum_amount" inputmode="decimal"></label>
                        <label>Importo massimo<input class="form-control form-control-sm" name="maximum_amount" inputmode="decimal"></label>
                        <label>Totale sanzione<input class="form-control form-control-sm" name="total_sanction_amount" inputmode="decimal"></label>
                    </div>
                </section>
                <section class="panel">
                    <h2>Profili penali e sequestri</h2>
                    <div class="form-grid">
                        <label>Reato contestato<textarea class="form-control form-control-sm" name="alleged_crime"></textarea></label>
                        <label>Numero CNR<input class="form-control form-control-sm" name="cnr_number"></label>
                        <label>Sequestro amministrativo
                            <select class="form-select form-select-sm" name="administrative_seizure">
                                <option value="0">No</option><option value="1">Si</option>
                            </select>
                        </label>
                        <label>Descrizione sequestro amministrativo<textarea class="form-control form-control-sm" name="administrative_seizure_description"></textarea></label>
                        <label>Sequestro penale
                            <select class="form-select form-select-sm" name="criminal_seizure">
                                <option value="0">No</option><option value="1">Si</option>
                            </select>
                        </label>
                        <label>Descrizione sequestro penale<textarea class="form-control form-control-sm" name="criminal_seizure_description"></textarea></label>
                        <label>Ritiro cautelativo arma
                            <select class="form-select form-select-sm" name="weapon_precautionary_withdrawal">
                                <option value="0">No</option><option value="1">Si</option>
                            </select>
                        </label>
                        <label>Descrizione ritiro arma<textarea class="form-control form-control-sm" name="weapon_precautionary_withdrawal_description"></textarea></label>
                    </div>
                </section>
                <section class="panel">
                    <h2>Note e salvataggio</h2>
                    <label>Note operative<textarea class="form-control form-control-sm" name="notes"></textarea></label>
                    <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit">Salva bozza</button></div>
                </section>
            </form>
        </main>
        HTML;

        return $this->view('Nuovo controllo', $content);
    }

    public function show(string $control): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        if (!ctype_digit($control)) {
            return new Response('<main class="container py-4"><h1>Controllo non trovato</h1></main>', 404);
        }

        $controlData = (new ControlService())->find((int) $control);

        if ($controlData === null) {
            return new Response('<main class="container py-4"><h1>Controllo non trovato</h1></main>', 404);
        }

        (new AuditService())->record(AuditActions::CONTROL_VIEWED, 'controls', (int) $control, 'Accesso dettaglio controllo');

        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-success py-2">' . $this->e($flash) . '</div>' : '';
        $registry = $this->e($controlData['registry_number'] . '/' . $controlData['registry_year']);
        $categories = $this->categoryBadges($controlData['categories']);
        $agents = $this->agentBadges($controlData['agents']);
        $versions = $this->versionRows($controlData['versions']);
        $actions = $this->detailActions($controlData);
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Controllo {$registry}</h1>
                    <p>Dettaglio registro amministrativo.</p>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="/controls">Torna all'elenco</a>
            </div>
            {$flashHtml}
            <div class="detail-grid">
                <section class="panel">
                    <h2>Dati generali</h2>
                    <dl class="detail-list">
                        <dt>Data</dt><dd>{$this->e($controlData['control_date'])} {$this->e($controlData['control_time'])}</dd>
                        <dt>Evento</dt><dd>{$this->e($controlData['event_name'])}</dd>
                        <dt>Attivita</dt><dd>{$this->e($controlData['business_name'])}</dd>
                        <dt>Luogo</dt><dd>{$this->e($controlData['business_location'])}</dd>
                        <dt>Esito</dt><dd>{$this->e($controlData['outcome'])}</dd>
                        <dt>Stato</dt><dd>{$this->e($controlData['status'])}</dd>
                    </dl>
                </section>
                <section class="panel">
                    <h2>Dati personali cifrati</h2>
                    <dl class="detail-list">
                        <dt>Titolare</dt><dd>{$this->e($controlData['business_owner'] ?? '')}</dd>
                        <dt>Trasgressore</dt><dd>{$this->e($controlData['offender'] ?? '')}</dd>
                        <dt>CNR</dt><dd>{$this->e($controlData['cnr_number'] ?? '')}</dd>
                        <dt>Note</dt><dd>{$this->e($controlData['notes'] ?? '')}</dd>
                    </dl>
                </section>
                <section class="panel">
                    <h2>Categorie</h2>
                    <div class="badge-list">{$categories}</div>
                </section>
                <section class="panel">
                    <h2>Agenti</h2>
                    <div class="badge-list">{$agents}</div>
                </section>
                <section class="panel">
                    <h2>Violazioni e sanzioni</h2>
                    <dl class="detail-list">
                        <dt>Norme violate</dt><dd>{$this->e($controlData['violated_rules'] ?? '')}</dd>
                        <dt>Norme sanzionatrici</dt><dd>{$this->e($controlData['sanctioning_rules'] ?? '')}</dd>
                        <dt>Totale sanzione</dt><dd>EUR {$this->e($controlData['total_sanction_amount'] ?? '0.00')}</dd>
                    </dl>
                </section>
                <section class="panel">
                    <h2>Hash e versioni</h2>
                    <dl class="detail-list">
                        <dt>Hash corrente</dt><dd><code>{$this->e($controlData['hash_record'])}</code></dd>
                        <dt>Hash precedente</dt><dd><code>{$this->e($controlData['previous_hash'] ?? '')}</code></dd>
                    </dl>
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Versione</th><th>Motivo</th><th>Utente</th><th>Data</th></tr></thead>
                        <tbody>{$versions}</tbody>
                    </table>
                </section>
            </div>
            {$actions}
        </main>
        HTML;

        return $this->view('Dettaglio controllo', $content);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio', 'operatore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();
        $data = $this->controlData($request);
        $validationError = $this->validateControlData($data);

        if ($user === null || $validationError !== null) {
            $this->flash($validationError ?? 'Utente non valido.');

            return $this->redirect('/controls/create');
        }

        try {
            $controlId = (new ControlService())->create($data, (int) $user['id']);
        } catch (Throwable $exception) {
            $this->flash('Impossibile salvare il controllo: ' . $exception->getMessage());

            return $this->redirect('/controls/create');
        }

        $this->flash('Controllo salvato in bozza.');

        return $this->redirect('/controls');
    }

    public function validate(string $control): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();

        try {
            (new ControlService())->validate((int) $control, (int) $user['id']);
            $this->flash('Controllo validato correttamente.');
        } catch (Throwable $exception) {
            $this->flash('Validazione non riuscita: ' . $exception->getMessage());
        }

        return $this->redirect('/controls/' . $control);
    }

    public function annul(string $control): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();

        try {
            (new ControlService())->annul((int) $control, (int) $user['id'], $request->input('annulment_reason', '') ?? '');
            $this->flash('Controllo annullato logicamente.');
        } catch (Throwable $exception) {
            $this->flash('Annullamento non riuscito: ' . $exception->getMessage());
        }

        return $this->redirect('/controls/' . $control);
    }

    private function controlData(Request $request): array
    {
        return [
            'control_date' => $request->input('control_date', '') ?? '',
            'control_time' => $request->input('control_time', '') ?? '',
            'has_event' => $request->input('has_event', '0') === '1' ? 1 : 0,
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
            'administrative_seizure' => $request->input('administrative_seizure', '0') === '1' ? 1 : 0,
            'administrative_seizure_description' => $request->input('administrative_seizure_description', '') ?? '',
            'criminal_seizure' => $request->input('criminal_seizure', '0') === '1' ? 1 : 0,
            'criminal_seizure_description' => $request->input('criminal_seizure_description', '') ?? '',
            'weapon_precautionary_withdrawal' => $request->input('weapon_precautionary_withdrawal', '0') === '1' ? 1 : 0,
            'weapon_precautionary_withdrawal_description' => $request->input('weapon_precautionary_withdrawal_description', '') ?? '',
            'notes' => $request->input('notes', '') ?? '',
        ];
    }

    private function validateControlData(array $data): ?string
    {
        if ($data['control_date'] === '' || $data['control_time'] === '') {
            return 'Data e ora controllo sono obbligatorie.';
        }

        if ((int) $data['has_event'] === 1 && trim((string) $data['event_name']) === '') {
            return 'Il nome evento e obbligatorio quando il controllo e collegato a evento.';
        }

        if ($data['business_name'] === '' || $data['business_location'] === '') {
            return 'Nome e luogo attivita sono obbligatori.';
        }

        if (!ctype_digit((string) $data['primary_category_id'])) {
            return 'La categoria principale e obbligatoria.';
        }

        if (!in_array($data['outcome'], ['positivo', 'negativo', 'in_accertamento'], true)) {
            return 'Esito controllo non valido.';
        }

        return null;
    }

    private function options(array $items, string $valueKey, string $labelKey): string
    {
        $options = '';

        foreach ($items as $item) {
            $value = $this->e($item[$valueKey]);
            $label = $this->e($item[$labelKey]);
            $options .= "<option value=\"{$value}\">{$label}</option>";
        }

        return $options;
    }

    private function agentOptions(array $agents): string
    {
        $options = '';

        foreach ($agents as $agent) {
            if ((int) $agent['active'] !== 1) {
                continue;
            }

            $value = $this->e($agent['id']);
            $label = $this->e(trim($agent['surname'] . ' ' . $agent['name']));
            $options .= "<option value=\"{$value}\">{$label}</option>";
        }

        return $options;
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

    private function selectOption(string $value, string $label, string $selected): string
    {
        $selectedAttribute = $value === $selected ? ' selected' : '';

        return '<option value="' . $this->e($value) . '"' . $selectedAttribute . '>' . $this->e($label) . '</option>';
    }

    private function optionsWithSelected(array $items, string $valueKey, string $labelKey, string $selected): string
    {
        $options = '';

        foreach ($items as $item) {
            $value = (string) $item[$valueKey];
            $selectedAttribute = $value === $selected ? ' selected' : '';
            $options .= '<option value="' . $this->e($value) . '"' . $selectedAttribute . '>' . $this->e($item[$labelKey]) . '</option>';
        }

        return $options;
    }

    private function agentOptionsWithSelected(array $agents, string $selected): string
    {
        $options = '';

        foreach ($agents as $agent) {
            if ((int) $agent['active'] !== 1) {
                continue;
            }

            $value = (string) $agent['id'];
            $label = trim($agent['surname'] . ' ' . $agent['name']);
            $selectedAttribute = $value === $selected ? ' selected' : '';
            $options .= '<option value="' . $this->e($value) . '"' . $selectedAttribute . '>' . $this->e($label) . '</option>';
        }

        return $options;
    }

    private function categoryBadges(array $categories): string
    {
        if ($categories === []) {
            return '<span class="text-muted">Nessuna categoria.</span>';
        }

        $badges = [];

        foreach ($categories as $category) {
            $type = (int) $category['is_primary'] === 1 ? 'principale' : 'secondaria';
            $badges[] = '<span class="status-pill">' . $this->e($category['name']) . ' (' . $type . ')</span>';
        }

        return implode('', $badges);
    }

    private function agentBadges(array $agents): string
    {
        if ($agents === []) {
            return '<span class="text-muted">Nessun agente collegato.</span>';
        }

        $badges = [];

        foreach ($agents as $agent) {
            $label = trim($agent['surname'] . ' ' . $agent['name']);
            $badges[] = '<span class="status-pill">' . $this->e($label) . '</span>';
        }

        return implode('', $badges);
    }

    private function versionRows(array $versions): string
    {
        if ($versions === []) {
            return '<tr><td colspan="4" class="text-muted">Nessuna versione.</td></tr>';
        }

        $rows = [];

        foreach ($versions as $version) {
            $rows[] = '<tr>'
                . '<td>' . $this->e($version['version_number']) . '</td>'
                . '<td>' . $this->e($version['change_reason']) . '</td>'
                . '<td>' . $this->e($version['changed_by_username'] ?? '') . '</td>'
                . '<td>' . $this->e($version['created_at']) . '</td>'
                . '</tr>';
        }

        return implode('', $rows);
    }

    private function detailActions(array $control): string
    {
        if (!$this->auth()->hasRole(['amministratore', 'responsabile_ufficio']) || $control['status'] === 'annullato') {
            return '';
        }

        $csrf = $this->csrfField();
        $controlId = $this->e($control['id']);
        $validateButton = $control['status'] === 'bozza'
            ? "<form method=\"post\" action=\"/controls/{$controlId}/validate\">{$csrf}<button class=\"btn btn-sm btn-success\" type=\"submit\">Valida controllo</button></form>"
            : '';

        return <<<HTML
        <section class="panel mt-3">
            <h2>Azioni responsabile</h2>
            <div class="action-row">
                {$validateButton}
                <form method="post" action="/controls/{$controlId}/annul" class="inline-annul-form">
                    {$csrf}
                    <input class="form-control form-control-sm" name="annulment_reason" placeholder="Motivo annullamento" required>
                    <button class="btn btn-sm btn-outline-danger" type="submit">Annulla logicamente</button>
                </form>
            </div>
        </section>
        HTML;
    }
}
