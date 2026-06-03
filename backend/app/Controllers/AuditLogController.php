<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;
use Prometheus\Services\AuditLogService;

final class AuditLogController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'logs' => (new AuditLogService())->latest(),
        ]);
    }
}
