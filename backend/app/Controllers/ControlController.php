<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\ActivityCategoryService;
use Prometheus\Services\AgentService;
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

        $controls = (new ControlService())->latest();
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-success py-2">' . $this->e($flash) . '</div>' : '';
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
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="text-muted">Nessun controllo inserito.</td></tr>';
        }

        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Ricerca controlli</h1>
                    <p>Elenco iniziale degli ultimi controlli inseriti.</p>
                </div>
                <a class="btn btn-sm btn-primary" href="/controls/create">Nuovo controllo</a>
            </div>
            {$flashHtml}
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Registro</th><th>Data</th><th>Evento</th><th>Attivita</th><th>Luogo</th><th>Esito</th><th>Stato</th></tr></thead>
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
}
