<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\BackupService;
use Throwable;

final class BackupController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'backups' => (new BackupService())->latest(),
        ]);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $user = $this->auth()->user();

        try {
            $backup = (new BackupService())->create((int) $user['id'], dirname(__DIR__, 2));
        } catch (Throwable $exception) {
            return $this->error('Backup non riuscito: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok' => true,
            'message' => 'Backup creato.',
            'backup' => $backup,
        ], 201);
    }
}
