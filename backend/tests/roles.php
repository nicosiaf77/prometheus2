<?php

/**
 * Test automatico accessi per ruolo — Prometheus2
 *
 * Verifica che ogni endpoint risponda con il codice HTTP corretto
 * per ciascun ruolo: amministratore, responsabile_ufficio, operatore, lettore.
 *
 * Uso:
 *   PROMETHEUS_API_BASE=http://localhost:8080 php tests/roles.php
 *
 * Richiede quattro utenti di test già presenti nel database.
 * Credenziali configurabili via variabili d'ambiente.
 */

declare(strict_types=1);

$baseUrl = rtrim((string) (getenv('PROMETHEUS_API_BASE') ?: 'http://localhost:8080'), '/');

// ── Credenziali utenti di test ────────────────────────────────────────────────
$credentials = [
    'amministratore'    => [
        'user' => getenv('TEST_ADMIN_USER')    ?: 'nicosiaf77',
        'pass' => getenv('TEST_ADMIN_PASS')    ?: '',
    ],
    'responsabile_ufficio' => [
        'user' => getenv('TEST_MANAGER_USER')  ?: '',
        'pass' => getenv('TEST_MANAGER_PASS')  ?: '',
    ],
    'operatore' => [
        'user' => getenv('TEST_OPERATOR_USER') ?: '',
        'pass' => getenv('TEST_OPERATOR_PASS') ?: '',
    ],
    'lettore' => [
        'user' => getenv('TEST_READER_USER')   ?: '',
        'pass' => getenv('TEST_READER_PASS')   ?: '',
    ],
];

// ── Matrice endpoint → ruoli autorizzati ─────────────────────────────────────
// Formato: 'METODO /path' => [ruoli_che_ricevono_200_o_201]
// I ruoli NON presenti riceveranno 401 (non autenticato) o 403 (permessi).
$matrix = [
    // Autenticazione — tutti
    'GET /dashboard'               => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    'GET /me'                      => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],

    // Controlli — tutti in lettura
    'GET /controls'                => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    'GET /controls/create'         => ['amministratore', 'responsabile_ufficio', 'operatore'],
    'GET /controls/1'              => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    'GET /controls/1/versions'     => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    // /edit su controllo validato: operatore ottiene 403 (solo bozze modificabili)
    'GET /controls/1/edit'         => ['amministratore', 'responsabile_ufficio'],
    // /edit su controllo in bozza: operatore ottiene 200
    'GET /controls/3/edit'         => ['amministratore', 'responsabile_ufficio', 'operatore'],

    // Tabelle di supporto — tutti in lettura
    'GET /events'                  => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    'GET /activity-categories'     => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    'GET /agents'                  => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],

    // Statistiche e report — tutti in lettura
    'GET /statistics'              => ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'],
    'GET /reports'                 => ['amministratore', 'responsabile_ufficio'],

    // Export — solo admin e responsabile
    'GET /reports/controls.csv'    => ['amministratore', 'responsabile_ufficio'],
    'GET /reports/controls.xlsx'   => ['amministratore', 'responsabile_ufficio'],
    'GET /reports/controls.pdf'    => ['amministratore', 'responsabile_ufficio'],
    'GET /reports/statistics.pdf'  => ['amministratore', 'responsabile_ufficio'],

    // Utenti — solo admin
    'GET /users'                   => ['amministratore'],

    // Audit log — admin e responsabile
    'GET /audit-logs'              => ['amministratore', 'responsabile_ufficio'],

    // Backup — solo admin
    'GET /backup'                  => ['amministratore'],

    // Integrità — admin e responsabile
    'GET /integrity-check'         => ['amministratore', 'responsabile_ufficio'],
];

// ── Helpers ───────────────────────────────────────────────────────────────────
$cookies = [];

function apiRequest(string $method, string $path): array
{
    global $baseUrl, $cookies;

    $headers = ['Accept: application/json'];

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
            'ignore_errors' => true,
        ],
    ]);

    $body        = file_get_contents($baseUrl . $path, false, $context);
    $statusLine  = $http_response_header[0] ?? 'HTTP/1.1 0';
    preg_match('/\s(\d{3})\s/', $statusLine, $m);

    return ['status' => isset($m[1]) ? (int) $m[1] : 0, 'body' => (string) $body];
}

function login(string $identifier, string $password): bool
{
    global $baseUrl, $cookies;

    $cookies = [];

    // CSRF
    $csrfCtx  = stream_context_create(['http' => ['method' => 'GET', 'header' => 'Accept: application/json', 'ignore_errors' => true]]);
    $csrfBody = file_get_contents($baseUrl . '/csrf-token', false, $csrfCtx);

    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $mm)) {
            $cookies[$mm[1]] = $mm[2];
        }
    }

    $csrfToken = json_decode((string) $csrfBody, true)['csrf_token'] ?? '';

    $payload  = json_encode(['identifier' => $identifier, 'password' => $password, '_csrf_token' => $csrfToken]);
    $headers  = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Cookie: ' . implode('; ', array_map(static fn ($k, $v) => "{$k}={$v}", array_keys($cookies), $cookies)),
    ];
    $loginCtx = stream_context_create([
        'http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'ignore_errors' => true],
    ]);
    $loginBody = file_get_contents($baseUrl . '/login', false, $loginCtx);

    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $mm)) {
            $cookies[$mm[1]] = $mm[2];
        }
    }

    $response = json_decode((string) $loginBody, true);

    return isset($response['ok']) && $response['ok'] === true;
}

function logout(): void
{
    global $baseUrl, $cookies;

    $csrfCtx  = stream_context_create(['http' => ['method' => 'GET', 'header' => 'Accept: application/json', 'ignore_errors' => true]]);
    $csrfBody = file_get_contents($baseUrl . '/csrf-token', false, $csrfCtx);

    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $mm)) {
            $cookies[$mm[1]] = $mm[2];
        }
    }

    $csrfToken = json_decode((string) $csrfBody, true)['csrf_token'] ?? '';
    $payload   = json_encode(['_csrf_token' => $csrfToken]);
    $headers   = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Cookie: ' . implode('; ', array_map(static fn ($k, $v) => "{$k}={$v}", array_keys($cookies), $cookies)),
    ];
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'ignore_errors' => true]]);
    file_get_contents($baseUrl . '/logout', false, $ctx);
    $cookies = [];
}

// ── Esecuzione ────────────────────────────────────────────────────────────────
$pass  = 0;
$fail  = 0;
$skip  = 0;
$fails = [];

echo "Test accessi per ruolo — Prometheus2\n";
echo str_repeat('=', 60) . "\n\n";

foreach ($credentials as $role => $cred) {
    if ($cred['user'] === '' || $cred['pass'] === '') {
        echo "SKIP [{$role}] — credenziali non configurate\n";
        ++$skip;

        continue;
    }

    if (!login($cred['user'], $cred['pass'])) {
        echo "ERRORE [{$role}] — login fallito per '{$cred['user']}'\n";
        ++$fail;

        continue;
    }

    echo "[{$role}]\n";

    foreach ($matrix as $endpoint => $authorizedRoles) {
        [$method, $path] = explode(' ', $endpoint, 2);
        $response        = apiRequest($method, $path);
        $status          = $response['status'];
        $isAuthorized    = in_array($role, $authorizedRoles, true);

        if ($isAuthorized) {
            // Ruolo autorizzato: accetta 200, 201, o qualsiasi 2xx
            $ok = $status >= 200 && $status < 300;
        } else {
            // Ruolo non autorizzato: attende 401 o 403
            $ok = in_array($status, [401, 403], true);
        }

        $label    = $isAuthorized ? "  autorizzato" : "  non-autorizzato";
        $expected = $isAuthorized ? '2xx' : '401/403';

        if ($ok) {
            echo "  OK  {$method} {$path} → HTTP {$status} ({$label})\n";
            ++$pass;
        } else {
            echo "  KO  {$method} {$path} → HTTP {$status} (atteso {$expected}) [{$label}]\n";
            $fails[] = "[{$role}] {$method} {$path} → HTTP {$status} (atteso {$expected})";
            ++$fail;
        }
    }

    logout();
    echo "\n";
}

// ── Riepilogo ─────────────────────────────────────────────────────────────────
echo str_repeat('=', 60) . "\n";
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
