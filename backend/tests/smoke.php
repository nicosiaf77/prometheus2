<?php

declare(strict_types=1);

$baseUrl = rtrim(getenv('PROMETHEUS_API_BASE') ?: 'http://localhost:8080', '/');
$username = getenv('PROMETHEUS_TEST_USER') ?: '';
$password = getenv('PROMETHEUS_TEST_PASSWORD') ?: '';
$cookies = [];

if ($username === '' || $password === '') {
    fwrite(STDERR, "Imposta PROMETHEUS_TEST_USER e PROMETHEUS_TEST_PASSWORD.\n");
    exit(2);
}

function request(string $method, string $path, ?array $payload = null): array
{
    global $baseUrl, $cookies;

    $headers = ['Accept: application/json'];
    $body = null;

    if ($payload !== null) {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers[] = 'Content-Type: application/json';
    }

    if ($cookies !== []) {
        $cookiePairs = [];
        foreach ($cookies as $name => $value) {
            $cookiePairs[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $cookiePairs);
    }

    $responseHeaders = [];
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
        ],
    ]);
    $body = file_get_contents($baseUrl . $path, false, $context);

    foreach ($http_response_header ?? [] as $header) {
        $responseHeaders[] = $header;
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $matches) === 1) {
            $cookies[$matches[1]] = $matches[2];
        }
    }

    $statusLine = $responseHeaders[0] ?? 'HTTP/1.1 0';
    preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch);
    $status = isset($statusMatch[1]) ? (int) $statusMatch[1] : 0;
    $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;

    return [
        'status' => $status,
        'body' => $body,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

function assertStatus(string $label, array $response, array $acceptedStatuses = [200]): void
{
    if (!in_array($response['status'], $acceptedStatuses, true)) {
        fwrite(STDERR, "KO {$label}: HTTP {$response['status']}\n{$response['body']}\n");
        exit(1);
    }

    echo "OK {$label}: HTTP {$response['status']}\n";
}

$unauthorized = request('GET', '/dashboard');
assertStatus('dashboard non autenticata', $unauthorized, [401]);

$csrf = request('GET', '/csrf-token');
assertStatus('csrf token', $csrf);
$token = $csrf['json']['csrf_token'] ?? '';

$login = request('POST', '/login', [
    '_csrf_token' => $token,
    'identifier' => $username,
    'password' => $password,
]);
assertStatus('login', $login);

$endpoints = [
    'utente corrente' => '/me',
    'dashboard' => '/dashboard',
    'controlli ricerca paginata' => '/controls?page=1&per_page=10&sort=control_date&direction=desc',
    'metadati nuovo controllo' => '/controls/create',
    'categorie' => '/activity-categories',
    'agenti' => '/agents',
    'eventi' => '/events',
    'statistiche' => '/statistics',
    'report catalogo' => '/reports',
    'utenti' => '/users',
    'audit log' => '/audit-logs',
    'backup elenco' => '/backup',
    'integrita info' => '/integrity-check',
];

foreach ($endpoints as $label => $path) {
    $response = request('GET', $path);
    assertStatus($label, $response, [200, 403]);
}

$csv = request('GET', '/reports/controls.csv?registry_year=' . date('Y'));
assertStatus('export CSV', $csv, [200, 403]);

$csrf = request('GET', '/csrf-token');
$logout = request('POST', '/logout', ['_csrf_token' => $csrf['json']['csrf_token'] ?? '']);
assertStatus('logout', $logout);

echo "Smoke test completato.\n";
