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
        $result = match ($action) {
            'csrf' => apiRequest('GET', '/csrf-token'),
            'login' => apiRequest('POST', '/login', [
                '_csrf_token' => csrfToken(),
                'identifier' => $_POST['identifier'] ?? '',
                'password' => $_POST['password'] ?? '',
            ]),
            'logout' => apiRequest('POST', '/logout', ['_csrf_token' => csrfToken()]),
            'me' => apiRequest('GET', '/me'),
            'dashboard' => apiRequest('GET', '/dashboard'),
            'controls' => apiRequest('GET', '/controls?page=1&per_page=10&sort=control_date&direction=desc'),
            'control_meta' => apiRequest('GET', '/controls/create'),
            'control_detail' => apiRequest('GET', '/controls/' . urlencode((string) ($_POST['control_id'] ?? '1'))),
            'categories' => apiRequest('GET', '/activity-categories'),
            'agents' => apiRequest('GET', '/agents'),
            'events' => apiRequest('GET', '/events'),
            'statistics' => apiRequest('GET', '/statistics'),
            'reports' => apiRequest('GET', '/reports'),
            'users' => apiRequest('GET', '/users'),
            'audit' => apiRequest('GET', '/audit-logs'),
            'backup' => apiRequest('GET', '/backup'),
            'integrity' => apiRequest('GET', '/integrity-check'),
            'integrity_run' => apiRequest('POST', '/integrity-check', ['_csrf_token' => csrfToken()]),
            'create_event' => apiRequest('POST', '/events', [
                '_csrf_token' => csrfToken(),
                'name' => $_POST['event_name'] ?? '',
            ]),
            'create_agent' => apiRequest('POST', '/agents', [
                '_csrf_token' => csrfToken(),
                'surname' => $_POST['agent_surname'] ?? '',
                'name' => $_POST['agent_name'] ?? '',
                'rank' => $_POST['agent_rank'] ?? '',
                'office' => $_POST['agent_office'] ?? '',
            ]),
            'create_control' => apiRequest('POST', '/controls', controlPayload()),
            default => ['status' => 400, 'body' => 'Azione test non valida.', 'headers' => []],
        };
    }
}

function csrfToken(): string
{
    $response = apiRequest('GET', '/csrf-token');
    $decoded = json_decode($response['body'], true);

    return is_array($decoded) ? (string) ($decoded['csrf_token'] ?? '') : '';
}

function apiRequest(string $method, string $path, ?array $payload = null): array
{
    global $apiBase;

    $headers = ['Accept: application/json'];
    $body = null;

    if ($payload !== null) {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers[] = 'Content-Type: application/json';
    }

    if ($_SESSION['api_cookies'] !== []) {
        $cookiePairs = [];
        foreach ($_SESSION['api_cookies'] as $name => $value) {
            $cookiePairs[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $cookiePairs);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = file_get_contents($apiBase . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];

    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $matches) === 1) {
            $_SESSION['api_cookies'][$matches[1]] = $matches[2];
        }
    }

    $statusLine = $responseHeaders[0] ?? 'HTTP/1.1 0';
    preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch);

    return [
        'status' => isset($statusMatch[1]) ? (int) $statusMatch[1] : 0,
        'body' => is_string($responseBody) ? $responseBody : '',
        'headers' => $responseHeaders,
    ];
}

function controlPayload(): array
{
    return [
        '_csrf_token' => csrfToken(),
        'control_date' => $_POST['control_date'] ?? date('Y-m-d'),
        'control_time' => $_POST['control_time'] ?? date('H:i'),
        'has_event' => $_POST['has_event'] ?? '0',
        'event_name' => $_POST['control_event_name'] ?? '',
        'business_name' => $_POST['business_name'] ?? '',
        'business_location' => $_POST['business_location'] ?? '',
        'business_owner' => $_POST['business_owner'] ?? '',
        'offender' => $_POST['offender'] ?? '',
        'primary_category_id' => $_POST['primary_category_id'] ?? '',
        'secondary_category_ids' => array_filter(explode(',', (string) ($_POST['secondary_category_ids'] ?? ''))),
        'agent_ids' => array_filter(explode(',', (string) ($_POST['agent_ids'] ?? ''))),
        'outcome' => $_POST['outcome'] ?? 'positivo',
        'violated_rules' => $_POST['violated_rules'] ?? '',
        'sanctioning_rules' => $_POST['sanctioning_rules'] ?? '',
        'total_sanction_amount' => $_POST['total_sanction_amount'] ?? '',
        'notes' => $_POST['notes'] ?? '',
    ];
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$prettyBody = '';
if ($result !== null) {
    $decoded = json_decode($result['body'], true);
    $prettyBody = is_array($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $result['body'];
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prometheus2 backend test harness</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fb; color: #182033; }
        header { background: #101828; color: white; padding: 14px 22px; display: flex; justify-content: space-between; gap: 16px; align-items: center; }
        main { display: grid; grid-template-columns: 360px 1fr; gap: 18px; padding: 18px; }
        section { background: white; border: 1px solid #e4e7ec; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 8px 24px rgb(16 24 40 / 5%); }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 14px; margin: 0 0 12px; color: #344054; }
        label { display: block; font-size: 12px; font-weight: 650; margin: 8px 0 4px; color: #475467; }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d0d5dd; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
        button { border: 0; border-radius: 8px; background: #175cd3; color: white; padding: 8px 11px; font-size: 13px; cursor: pointer; margin: 4px 4px 4px 0; }
        button.secondary { background: #475467; }
        button.warning { background: #b42318; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        pre { min-height: 520px; white-space: pre-wrap; word-break: break-word; background: #0b1220; color: #d1e9ff; padding: 16px; border-radius: 12px; overflow: auto; }
        .status { font-size: 13px; opacity: .9; }
    </style>
</head>
<body>
<header>
    <h1>Prometheus2 · Backend test harness</h1>
    <div class="status">API: <?= e($apiBase) ?> · Cookie test: <?= e(count($_SESSION['api_cookies'])) ?></div>
</header>
<main>
    <div>
        <section>
            <h2>Connessione</h2>
            <form method="post">
                <label>Base API</label>
                <input name="api_base" value="<?= e($apiBase) ?>">
                <button name="action" value="csrf">Leggi CSRF</button>
                <button class="secondary" name="action" value="reset">Reset cookie test</button>
            </form>
        </section>
        <section>
            <h2>Autenticazione</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Username/email</label>
                <input name="identifier" autocomplete="username">
                <label>Password</label>
                <input name="password" type="password" autocomplete="current-password">
                <button name="action" value="login">Login</button>
                <button class="secondary" name="action" value="logout">Logout</button>
            </form>
        </section>
        <section>
            <h2>Test lettura API</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <div class="grid">
                    <button name="action" value="dashboard">Dashboard</button>
                    <button name="action" value="me">Utente /me</button>
                    <button name="action" value="controls">Controlli</button>
                    <button name="action" value="control_meta">Meta controllo</button>
                    <button name="action" value="categories">Categorie</button>
                    <button name="action" value="agents">Agenti</button>
                    <button name="action" value="events">Eventi</button>
                    <button name="action" value="statistics">Statistiche</button>
                    <button name="action" value="reports">Report</button>
                    <button name="action" value="users">Utenti</button>
                    <button name="action" value="audit">Audit</button>
                    <button name="action" value="backup">Backup</button>
                    <button name="action" value="integrity">Integrità</button>
                </div>
                <label>ID controllo dettaglio</label>
                <input name="control_id" value="1">
                <button name="action" value="control_detail">Dettaglio controllo</button>
                <button name="action" value="integrity_run">Esegui integrità</button>
            </form>
        </section>
        <section>
            <h2>Test scrittura: evento/agente</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Nuovo evento</label>
                <input name="event_name" value="TEST HARNESS <?= e(date('Ymd-His')) ?>">
                <button name="action" value="create_event">Crea evento</button>
                <label>Cognome agente</label>
                <input name="agent_surname" value="Test">
                <label>Nome agente</label>
                <input name="agent_name" value="Harness">
                <label>Qualifica</label>
                <input name="agent_rank" value="Tester">
                <label>Ufficio</label>
                <input name="agent_office" value="Backend">
                <button name="action" value="create_agent">Crea agente</button>
            </form>
        </section>
        <section>
            <h2>Test scrittura: controllo</h2>
            <form method="post">
                <input type="hidden" name="api_base" value="<?= e($apiBase) ?>">
                <label>Data/Ora</label>
                <div class="grid"><input type="date" name="control_date" value="<?= e(date('Y-m-d')) ?>"><input type="time" name="control_time" value="<?= e(date('H:i')) ?>"></div>
                <label>Attività</label>
                <input name="business_name" value="Attività test backend">
                <label>Luogo</label>
                <input name="business_location" value="Comune test">
                <label>ID categoria primaria</label>
                <input name="primary_category_id" value="1">
                <label>ID agenti separati da virgola</label>
                <input name="agent_ids" value="1">
                <label>Esito</label>
                <select name="outcome"><option value="positivo">positivo</option><option value="negativo">negativo</option><option value="in_accertamento">in_accertamento</option></select>
                <label>Note</label>
                <textarea name="notes">Controllo creato da harness backend.</textarea>
                <button class="warning" name="action" value="create_control">Crea controllo test</button>
            </form>
        </section>
    </div>
    <section>
        <h2>Risposta API</h2>
        <p>HTTP <?= e($result['status'] ?? '—') ?></p>
        <pre><?= e($prettyBody ?: 'Esegui un test dal pannello a sinistra.') ?></pre>
    </section>
</main>
</body>
</html>
