<?php

/**
 * Test automatico accessi per ruolo — Prometheus2
 *
 * Verifica che ogni endpoint risponda con il codice HTTP corretto
 * per ciascun ruolo: amministratore, responsabile_ufficio, operatore, lettore.
 *
 * Per le rotte POST/PUT invia il CSRF token e un body minimale.
 * "Autorizzato"   = HTTP 2xx oppure 422 (validazione fallita ma role check passato).
 * "Non-autorizzato" = HTTP 401 oppure 403.
 * "CSRF invalido" = HTTP 419 — segnalato come errore di setup, non di permesso.
 *
 * Uso:
 *   PROMETHEUS_API_BASE=http://localhost:8080 php tests/roles.php
 *
 * Credenziali configurabili via variabili d'ambiente (vedi README.md).
 */

declare(strict_types=1);

$baseUrl = rtrim((string) (getenv('PROMETHEUS_API_BASE') ?: 'http://localhost:8080'), '/');

// ── Credenziali utenti di test ─────────────────────────────────────────────
$credentials = [
    'amministratore' => [
        'user' => getenv('TEST_ADMIN_USER')    ?: 'nicosiaf77',
        'pass' => getenv('TEST_ADMIN_PASS')    ?: '',
    ],
    'responsabile_ufficio' => [
        'user' => getenv('TEST_MANAGER_USER')  ?: 'responsabile_test',
        'pass' => getenv('TEST_MANAGER_PASS')  ?: '',
    ],
    'operatore' => [
        'user' => getenv('TEST_OPERATOR_USER') ?: 'operatore_test',
        'pass' => getenv('TEST_OPERATOR_PASS') ?: '',
    ],
    'lettore' => [
        'user' => getenv('TEST_READER_USER')   ?: 'lettore_test',
        'pass' => getenv('TEST_READER_PASS')   ?: '',
    ],
];

// ── Matrice endpoint → ruoli autorizzati ──────────────────────────────────
// Formato:  'METODO /path' => [ruoli_autorizzati, payload_opzionale]
// Ruoli NON presenti: atteso 401 o 403.
// POST/PUT: "autorizzato" = 2xx oppure 422 (role check ok, validazione fallita).
// GET:      "autorizzato" = 2xx.
$matrix = [

    // ── Auth e profilo ────────────────────────────────────────────────────
    'GET  /dashboard'    => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    'GET  /me'           => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],

    // POST /profile/change-password — tutti i ruoli (422 atteso se pw attuale mancante)
    'POST /profile/change-password' => [
        ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
        ['current_password' => '', 'password' => ''],
    ],

    // ── Controlli — lettura ───────────────────────────────────────────────
    'GET  /controls'         => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    'GET  /controls/create'  => [['amministratore', 'responsabile_ufficio', 'operatore']],
    'GET  /controls/1'       => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    'GET  /controls/1/versions' => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    // /edit su validato (1,3): operatore -> 403
    // /edit su bozza stabile (4, mai toccata dai test precedenti): operatore -> 200/422
    'GET  /controls/1/edit'  => [['amministratore', 'responsabile_ufficio']],
    'GET  /controls/4/edit'  => [['amministratore', 'responsabile_ufficio', 'operatore']],

    // ── Controlli — scrittura (POST/PUT) ──────────────────────────────────
    // POST /controls — 422 atteso (body vuoto) per i ruoli autorizzati
    'POST /controls' => [
        ['amministratore', 'responsabile_ufficio', 'operatore'],
        [
            'control_date'        => '',
            'control_time'        => '',
            'has_event'           => false,
            'business_name'       => '',
            'business_location'   => '',
            'primary_category_id' => '',
            'outcome'             => '',
        ],
    ],

    // PUT /controls/3 — 422 atteso (change_reason mancante) per i ruoli autorizzati
    'PUT  /controls/3' => [
        ['amministratore', 'responsabile_ufficio', 'operatore'],
        [
            'control_date'        => '2026-06-03',
            'control_time'        => '10:00',
            'has_event'           => false,
            'business_name'       => 'Test',
            'business_location'   => 'Test',
            'primary_category_id' => '1',
            'outcome'             => 'positivo',
            'change_reason'       => '',    // vuoto → 422
        ],
    ],

    // POST /controls/3/validate — solo admin e responsabile
    'POST /controls/3/validate' => [['amministratore', 'responsabile_ufficio'], []],

    // POST /controls/3/annul — solo admin e responsabile; 422 atteso (reason mancante)
    'POST /controls/3/annul' => [
        ['amministratore', 'responsabile_ufficio'],
        ['annulment_reason' => ''],
    ],

    // ── Tabelle di supporto — lettura ─────────────────────────────────────
    'GET  /events'            => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    'GET  /activity-categories' => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    'GET  /agents'            => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],

    // ── Tabelle di supporto — scrittura ──────────────────────────────────
    // POST /agents — 422 atteso per i ruoli autorizzati (nome mancante)
    'POST /agents' => [
        ['amministratore', 'responsabile_ufficio'],
        ['name' => '', 'surname' => ''],
    ],

    // PUT /agents/1 — 422 atteso per i ruoli autorizzati
    'PUT  /agents/1' => [
        ['amministratore', 'responsabile_ufficio'],
        ['name' => '', 'surname' => ''],
    ],

    // POST /agents/1/deactivate — solo admin e responsabile
    'POST /agents/1/deactivate' => [['amministratore', 'responsabile_ufficio'], []],

    // POST /activity-categories — 422 atteso (nome mancante)
    'POST /activity-categories' => [['amministratore'], ['name' => '']],

    // POST /events — 422 atteso (nome mancante)
    'POST /events' => [
        ['amministratore', 'responsabile_ufficio', 'operatore'],
        ['name' => ''],
    ],

    // ── Statistiche e report ──────────────────────────────────────────────
    'GET  /statistics'            => [['amministratore', 'responsabile_ufficio', 'operatore', 'lettore']],
    'GET  /reports'               => [['amministratore', 'responsabile_ufficio']],
    'GET  /reports/controls.csv'  => [['amministratore', 'responsabile_ufficio']],
    'GET  /reports/controls.xls'  => [['amministratore', 'responsabile_ufficio']],
    'GET  /reports/controls.pdf'  => [['amministratore', 'responsabile_ufficio']],
    'GET  /reports/statistics.pdf'=> [['amministratore', 'responsabile_ufficio']],

    // ── Utenti ────────────────────────────────────────────────────────────
    'GET  /users' => [['amministratore']],

    // POST /users — 422 atteso per admin (campi mancanti)
    'POST /users' => [
        ['amministratore'],
        ['name' => '', 'surname' => '', 'email' => '', 'username' => '', 'password' => '', 'role' => ''],
    ],

    // ── Audit log ────────────────────────────────────────────────────────
    'GET  /audit-logs' => [['amministratore', 'responsabile_ufficio']],

    // ── Backup ───────────────────────────────────────────────────────────
    'GET  /backup' => [['amministratore']],

    // POST /backup — solo admin
    'POST /backup' => [['amministratore'], []],

    // ── Integrità ─────────────────────────────────────────────────────────
    'GET  /integrity-check'  => [['amministratore', 'responsabile_ufficio']],

    // POST /integrity-check — solo admin e responsabile
    'POST /integrity-check'  => [['amministratore', 'responsabile_ufficio'], []],
];

// ── Helpers ───────────────────────────────────────────────────────────────
$cookies    = [];
$csrfCache  = null;

function apiRequest(string $method, string $path, ?array $body = null): array
{
    global $baseUrl, $cookies;

    $headers = ['Accept: application/json'];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(
            static fn (string $k, string $v): string => "{$k}={$v}",
            array_keys($cookies),
            $cookies
        ));
    }

    $context = stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : '',
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = file_get_contents($baseUrl . $path, false, $context);

    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }

    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 0';
    preg_match('/\s(\d{3})\s/', $statusLine, $m);

    return ['status' => isset($m[1]) ? (int) $m[1] : 0, 'body' => (string) $responseBody];
}

function getCsrf(): string
{
    global $csrfCache;

    if ($csrfCache !== null) {
        return $csrfCache;
    }

    $response = apiRequest('GET', '/csrf-token');
    $decoded  = json_decode($response['body'], true);
    $csrfCache = is_array($decoded) ? (string) ($decoded['csrf_token'] ?? '') : '';

    return $csrfCache;
}

function login(string $identifier, string $password): bool
{
    global $cookies, $csrfCache;

    $cookies   = [];
    $csrfCache = null;
    $token     = getCsrf();

    $response = apiRequest('POST', '/login', [
        '_csrf_token' => $token,
        'identifier'  => $identifier,
        'password'    => $password,
    ]);

    $csrfCache = null;   // invalida il cache dopo il login

    $decoded = json_decode($response['body'], true);

    return is_array($decoded) && ($decoded['ok'] ?? false) === true;
}

function logout(): void
{
    global $cookies, $csrfCache;

    apiRequest('POST', '/logout', ['_csrf_token' => getCsrf()]);
    $cookies   = [];
    $csrfCache = null;
}

// ── Esecuzione ────────────────────────────────────────────────────────────
$pass  = 0;
$fail  = 0;
$skip  = 0;
$fails = [];

echo "Test accessi per ruolo — Prometheus2\n";
echo str_repeat('=', 65) . "\n\n";

foreach ($credentials as $role => $cred) {
    if ($cred['user'] === '' || $cred['pass'] === '') {
        echo "SKIP [{$role}] — credenziali non configurate\n\n";
        ++$skip;

        continue;
    }

    if (!login($cred['user'], $cred['pass'])) {
        echo "ERRORE [{$role}] — login fallito per '{$cred['user']}'\n\n";
        ++$fail;

        continue;
    }

    echo "[{$role}]\n";

    foreach ($matrix as $endpoint => $config) {
        [$authorizedRoles, $payload] = array_pad($config, 2, null);

        // Parsing "METODO /path"
        [$httpMethod, $path] = array_pad(explode(' ', trim($endpoint), 2), 2, '');
        $httpMethod = strtoupper(trim($httpMethod));
        $path       = trim($path);
        $isPost     = in_array($httpMethod, ['POST', 'PUT', 'PATCH'], true);

        // Prepara il body per POST/PUT includendo il CSRF token
        $body = null;

        if ($isPost) {
            $body = array_merge(['_csrf_token' => getCsrf()], is_array($payload) ? $payload : []);
        }

        $response    = apiRequest($httpMethod, $path, $body);
        $status      = $response['status'];
        $isAuthorized = in_array($role, $authorizedRoles, true);

        if ($isAuthorized) {
            // Ruolo autorizzato: accetta 2xx o 422 (validazione fallita ma permesso ok)
            // 419 indica CSRF invalido — segnalato come errore di setup
            if ($status === 419) {
                echo "  !! {$httpMethod} {$path} → HTTP 419 (CSRF invalido — problema di setup)\n";
                $fails[] = "[{$role}] {$httpMethod} {$path} → HTTP 419 (CSRF invalido)";
                ++$fail;

                continue;
            }

            $ok = $status >= 200 && $status < 300 || $status === 422;
        } else {
            // Ruolo non autorizzato: attende 401 o 403
            $ok = in_array($status, [401, 403], true);
        }

        $label    = $isAuthorized ? 'autorizzato' : 'non-autorizzato';
        $expected = $isAuthorized ? '2xx/422' : '401/403';

        if ($ok) {
            echo "  OK  {$httpMethod} {$path} → HTTP {$status} ({$label})\n";
            ++$pass;
        } else {
            echo "  KO  {$httpMethod} {$path} → HTTP {$status} (atteso {$expected}) [{$label}]\n";
            $fails[] = "[{$role}] {$httpMethod} {$path} → HTTP {$status} (atteso {$expected})";
            ++$fail;
        }
    }

    logout();
    echo "\n";
}

// ── Riepilogo ─────────────────────────────────────────────────────────────
echo str_repeat('=', 65) . "\n";
echo "Risultato: {$pass} OK — {$fail} KO — {$skip} SKIP\n";

if ($fails !== []) {
    echo "\nFallimenti:\n";

    foreach ($fails as $f) {
        echo "  • {$f}\n";
    }

    exit(1);
}

echo $skip > 0 ? "Alcuni ruoli saltati per credenziali mancanti.\n" : "Tutti i test superati.\n";
exit(0);
