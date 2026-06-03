<?php

declare(strict_types=1);

session_start();

$apiBase = rtrim($_POST['api_base'] ?? $_SESSION['api_base'] ?? 'http://localhost:8080', '/');
$_SESSION['api_base'] = $apiBase;
$_SESSION['api_cookies'] ??= [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if ($action === 'reset') {
        $_SESSION['api_cookies'] = [];
        $result = ['status' => 200, 'body' => 'Cookie test cancellati.', 'headers' => []];
    } else {
        $controlId = urlencode((string) ($_POST['control_id'] ?? '1'));
        $userId    = urlencode((string) ($_POST['user_id'] ?? '1'));
        $agentId   = urlencode((string) ($_POST['agent_id'] ?? '1'));
        $categoryId = urlencode((string) ($_POST['category_id'] ?? '1'));
        $eventId   = urlencode((string) ($_POST['event_id'] ?? '1'));

        $result = match ($action) {
            // ── Auth ──────────────────────────────────────────────────────────
            'csrf'           => apiRequest('GET', '/csrf-token'),
            'login'          => apiRequest('POST', '/login', [
                '_csrf_token' => csrfToken(),
                'identifier'  => $_POST['identifier'] ?? '',
                'password'    => $_POST['password'] ?? '',
            ]),
            'logout'         => apiRequest('POST', '/logout', ['_csrf_token' => csrfToken()]),
            'me'             => apiRequest('GET', '/me'),

            // ── Dashboard ─────────────────────────────────────────────────────
            'dashboard'      => apiRequest('GET', '/dashboard'),

            // ── Controlli ─────────────────────────────────────────────────────
            'controls'       => apiRequest('GET', '/controls?page=1&per_page=10&sort=control_date&direction=desc'),
            'control_meta'   => apiRequest('GET', '/controls/create'),
            'control_detail' => apiRequest('GET', "/controls/{$controlId}"),
            'control_edit'   => apiRequest('GET', "/controls/{$controlId}/edit"),
            'control_versions' => apiRequest('GET', "/controls/{$controlId}/versions"),
            'control_pdf'    => apiRequest('GET', "/controls/{$controlId}/pdf"),
            'control_validate' => apiRequest('POST', "/controls/{$controlId}/validate", ['_csrf_token' => csrfToken()]),
            'control_annul'  => apiRequest('POST', "/controls/{$controlId}/annul", [
                '_csrf_token'      => csrfToken(),
                'annulment_reason' => $_POST['annulment_reason'] ?? 'Annullato da test harness',
            ]),
            'create_control' => apiRequest('POST', '/controls', controlPayload()),

            // ── Tabelle di supporto ───────────────────────────────────────────
            'categories'     => apiRequest('GET', '/activity-categories'),
            'agents'         => apiRequest('GET', '/agents'),
            'agent_detail'   => apiRequest('GET', "/agents/{$agentId}"),
            'events'         => apiRequest('GET', '/events'),
            'event_detail'   => apiRequest('GET', "/events/{$eventId}"),
            'create_event'   => apiRequest('POST', '/events', [
                '_csrf_token' => csrfToken(),
                'name'        => $_POST['event_name'] ?? '',
            ]),
            'create_agent'   => apiRequest('POST', '/agents', [
                '_csrf_token' => csrfToken(),
                'surname'     => $_POST['agent_surname'] ?? '',
                'name'        => $_POST['agent_name'] ?? '',
                'rank'        => $_POST['agent_rank'] ?? '',
                'office'      => $_POST['agent_office'] ?? '',
            ]),

            // ── Statistiche e report ──────────────────────────────────────────
            'statistics'     => apiRequest('GET', '/statistics'),
            'reports'        => apiRequest('GET', '/reports'),
            'csv'            => apiRequest('GET', '/reports/controls.csv'),
            'xlsx'           => apiRequest('GET', '/reports/controls.xls'),
            'controls_pdf'   => apiRequest('GET', '/reports/controls.pdf'),
            'stats_pdf'      => apiRequest('GET', '/reports/statistics.pdf'),

            // ── Amministrazione ───────────────────────────────────────────────
            'users'          => apiRequest('GET', '/users'),
            'user_detail'    => apiRequest('GET', "/users/{$userId}"),
            'audit'          => apiRequest('GET', '/audit-logs?per_page=20'),
            'audit_filtered' => apiRequest('GET', '/audit-logs?action=' . urlencode($_POST['audit_action'] ?? '') . '&per_page=20'),
            'backup'         => apiRequest('GET', '/backup'),
            'backup_create'  => apiRequest('POST', '/backup', ['_csrf_token' => csrfToken()]),
            'integrity'      => apiRequest('GET', '/integrity-check'),
            'integrity_run'  => apiRequest('POST', '/integrity-check', ['_csrf_token' => csrfToken()]),

            // ── Profilo ───────────────────────────────────────────────────────
            'profile_pw'     => apiRequest('POST', '/profile/change-password', [
                '_csrf_token'      => csrfToken(),
                'current_password' => $_POST['current_password'] ?? '',
                'password'         => $_POST['new_password'] ?? '',
            ]),

            default => ['status' => 400, 'body' => 'Azione test non valida.', 'headers' => []],
        };
    }
}

function csrfToken(): string
{
    $response = apiRequest('GET', '/csrf-token');
    $decoded  = json_decode($response['body'], true);

    return is_array($decoded) ? (string) ($decoded['csrf_token'] ?? '') : '';
}

function apiRequest(string $method, string $path, ?array $payload = null): array
{
    global $apiBase;

    $headers = ['Accept: application/json'];
    $body    = null;

    if ($payload !== null) {
        $body      = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers[] = 'Content-Type: application/json';
    }

    if ($_SESSION['api_cookies'] !== []) {
        $pairs = [];

        foreach ($_SESSION['api_cookies'] as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }

    $context = stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $body ?? '',
            'ignore_errors' => true,
        ],
    ]);

    $responseBody    = file_get_contents($apiBase . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];

    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $matches) === 1) {
            $_SESSION['api_cookies'][$matches[1]] = $matches[2];
        }
    }

    $statusLine = $responseHeaders[0] ?? 'HTTP/1.1 0';
    preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch);

    return [
        'status'  => isset($statusMatch[1]) ? (int) $statusMatch[1] : 0,
        'body'    => is_string($responseBody) ? $responseBody : '',
        'headers' => $responseHeaders,
    ];
}

function controlPayload(): array
{
    return [
        '_csrf_token'           => csrfToken(),
        'control_date'          => $_POST['control_date'] ?? date('Y-m-d'),
        'control_time'          => $_POST['control_time'] ?? date('H:i'),
        'has_event'             => $_POST['has_event'] ?? false,
        'event_name'            => $_POST['control_event_name'] ?? '',
        'business_name'         => $_POST['business_name'] ?? '',
        'business_location'     => $_POST['business_location'] ?? '',
        'business_owner'        => $_POST['business_owner'] ?? '',
        'offender'              => $_POST['offender'] ?? '',
        'primary_category_id'   => $_POST['primary_category_id'] ?? '',
        'secondary_category_ids'=> array_filter(explode(',', (string) ($_POST['secondary_category_ids'] ?? ''))),
        'agent_ids'             => array_filter(explode(',', (string) ($_POST['agent_ids'] ?? ''))),
        'outcome'               => $_POST['outcome'] ?? 'positivo',
        'violated_rules'        => $_POST['violated_rules'] ?? '',
        'sanctioning_rules'     => $_POST['sanctioning_rules'] ?? '',
        'total_sanction_amount' => $_POST['total_sanction_amount'] ?? '',
        'notes'                 => $_POST['notes'] ?? '',
    ];
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$prettyBody = '';

if ($result !== null) {
    $decoded    = json_decode($result['body'], true);
    $prettyBody = is_array($decoded)
        ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        : $result['body'];
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prometheus2 · Backend test harness</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fb; color: #182033; }
        header { background: #101828; color: white; padding: 14px 22px; display: flex; justify-content: space-between; gap: 16px; align-items: center; }
        main { display: grid; grid-template-columns: 380px 1fr; gap: 18px; padding: 18px; }
        section { background: white; border: 1px solid #e4e7ec; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 8px 24px rgb(16 24 40 / 5%); }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 13px; font-weight: 700; margin: 0 0 10px; color: #344054; text-transform: uppercase; letter-spacing: .03em; }
        label { display: block; font-size: 12px; font-weight: 650; margin: 8px 0 4px; color: #475467; }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d0d5dd; border-radius: 8px; padding: 7px 10px; font-size: 13px; }
        button { border: 0; border-radius: 8px; background: #175cd3; color: white; padding: 7px 11px; font-size: 12px; cursor: pointer; margin: 3px 3px 3px 0; }
        button.secondary { background: #475467; }
        button.warning { background: #b42318; }
        .grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
        pre { min-height: 520px; white-space: pre-wrap; word-break: break-word; background: #0b1220; color: #d1e9ff; padding: 16px; border-radius: 12px; overflow: auto; font-size: 12px; }
        .status { font-size: 13px; opacity: .9; }
        .http { font-size: 22px; font-weight: 700; margin: 0 0 8px; color: <?= isset($result) && $result['status'] >= 200 && $result['status'] < 300 ? '#027a48' : '#b42318' ?>; }
    </style>
</head>
<body>
<header>
    <h1>Prometheus2 · Backend test harness</h1>
    <div class="status">API: <?= e($apiBase) ?> &nbsp;|&nbsp; Cookie sessione: <?= e(count($_SESSION['api_cookies'])) ?></div>
</header>
<main>
    <div>
        <!-- Connessione -->
        <section>
            <h2>Connessione</h2>
            <form method="post">
                <label>Base API URL</label>
                <input name="api_base" value="<?= e($apiBase) ?>">
                <button name="action" value="csrf">Leggi CSRF token</button>
                <button class="secondary" name="action" value="reset">Reset cookie sessione</button>
            </form>
        </section>

        <!-- Auth -->
        <section>
            <h2>Autenticazione</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Username o email</label>
                <input name="identifier" autocomplete="username">
                <label>Password</label>
                <input name="password" type="password" autocomplete="current-password">
                <button name="action" value="login">Login</button>
                <button class="secondary" name="action" value="logout">Logout</button>
                <button class="secondary" name="action" value="me">GET /me</button>
            </form>
        </section>

        <!-- Lettura generale -->
        <section>
            <h2>Lettura API</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <div class="grid3">
                    <button name="action" value="dashboard">Dashboard</button>
                    <button name="action" value="controls">Controlli</button>
                    <button name="action" value="control_meta">Meta form</button>
                    <button name="action" value="categories">Categorie</button>
                    <button name="action" value="agents">Agenti</button>
                    <button name="action" value="events">Eventi</button>
                    <button name="action" value="statistics">Statistiche</button>
                    <button name="action" value="reports">Catalogo report</button>
                    <button name="action" value="users">Utenti</button>
                    <button name="action" value="audit">Audit log</button>
                    <button name="action" value="backup">Backup lista</button>
                    <button name="action" value="integrity">Integrità info</button>
                </div>
            </form>
        </section>

        <!-- Controllo singolo -->
        <section>
            <h2>Controllo per ID</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>ID controllo</label>
                <input name="control_id" value="1">
                <div class="grid2">
                    <button name="action" value="control_detail">Dettaglio</button>
                    <button name="action" value="control_edit">Form edit</button>
                    <button name="action" value="control_versions">Versioni</button>
                    <button name="action" value="control_pdf">PDF scheda</button>
                    <button name="action" value="control_validate">Valida</button>
                </div>
                <label>Motivo annullamento</label>
                <input name="annulment_reason" value="Test harness annullamento">
                <button class="warning" name="action" value="control_annul">Annulla</button>
            </form>
        </section>

        <!-- Dettaglio per ID -->
        <section>
            <h2>Dettaglio risorse</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <div class="grid3">
                    <div>
                        <label>ID agente</label>
                        <input name="agent_id" value="1">
                        <button name="action" value="agent_detail">Agente</button>
                    </div>
                    <div>
                        <label>ID evento</label>
                        <input name="event_id" value="1">
                        <button name="action" value="event_detail">Evento</button>
                    </div>
                    <div>
                        <label>ID utente</label>
                        <input name="user_id" value="1">
                        <button name="action" value="user_detail">Utente</button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Export -->
        <section>
            <h2>Export report</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <div class="grid2">
                    <button name="action" value="csv">CSV controlli</button>
                    <button name="action" value="xlsx">Excel controlli</button>
                    <button name="action" value="controls_pdf">PDF controlli</button>
                    <button name="action" value="stats_pdf">PDF statistiche</button>
                </div>
            </form>
        </section>

        <!-- Audit filtrato + integrità + backup -->
        <section>
            <h2>Amministrazione</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Filtro azione audit (es. CONTROL_CREATED)</label>
                <input name="audit_action" value="">
                <button name="action" value="audit_filtered">Audit filtrato</button>
                <button name="action" value="integrity_run">Esegui integrità</button>
                <button class="warning" name="action" value="backup_create">Crea backup</button>
            </form>
        </section>

        <!-- Profilo -->
        <section>
            <h2>Profilo — cambio password</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Password attuale</label>
                <input type="password" name="current_password">
                <label>Nuova password (min 8 car.)</label>
                <input type="password" name="new_password">
                <button name="action" value="profile_pw">Cambia password</button>
            </form>
        </section>

        <!-- Scrittura evento / agente -->
        <section>
            <h2>Crea evento / agente</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Nome evento</label>
                <input name="event_name" value="TEST HARNESS <?= e(date('Ymd-His')) ?>">
                <button name="action" value="create_event">Crea evento</button>
                <div class="grid2">
                    <div>
                        <label>Cognome</label><input name="agent_surname" value="Test">
                    </div>
                    <div>
                        <label>Nome</label><input name="agent_name" value="Harness">
                    </div>
                </div>
                <label>Qualifica</label><input name="agent_rank" value="Tester">
                <label>Ufficio</label><input name="agent_office" value="Backend QA">
                <button name="action" value="create_agent">Crea agente</button>
            </form>
        </section>

        <!-- Scrittura controllo -->
        <section>
            <h2>Crea controllo</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <div class="grid2">
                    <div><label>Data</label><input type="date" name="control_date" value="<?= e(date('Y-m-d')) ?>"></div>
                    <div><label>Ora</label><input type="time" name="control_time" value="<?= e(date('H:i')) ?>"></div>
                </div>
                <label>Nome attività</label>
                <input name="business_name" value="Attività test backend">
                <label>Luogo</label>
                <input name="business_location" value="Comune test">
                <label>ID categoria primaria</label>
                <input name="primary_category_id" value="1">
                <label>ID agenti (separati da virgola)</label>
                <input name="agent_ids" value="1">
                <label>Esito</label>
                <select name="outcome">
                    <option value="positivo">positivo</option>
                    <option value="negativo">negativo</option>
                    <option value="in_accertamento">in_accertamento</option>
                </select>
                <label>Note</label>
                <textarea name="notes" rows="2">Controllo creato da harness backend.</textarea>
                <button class="warning" name="action" value="create_control">Crea controllo test</button>
            </form>
        </section>
    </div>

    <!-- Pannello risposta -->
    <section>
        <h2>Risposta API</h2>
        <?php if ($result !== null): ?>
        <p class="http">HTTP <?= e($result['status']) ?></p>
        <?php endif; ?>
        <pre><?= e($prettyBody ?: 'Esegui un test dal pannello a sinistra.') ?></pre>
    </section>
</main>
</body>
</html>
