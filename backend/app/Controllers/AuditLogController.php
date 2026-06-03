<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Services\AuditLogService;

final class AuditLogController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore', 'responsabile_ufficio'])) {
            return $response;
        }

        $request = new Request();
        $filters = [
            'action'      => $request->input('action', '') ?? '',
            'entity_type' => $request->input('entity_type', '') ?? '',
            'date_from'   => $request->input('date_from', '') ?? '',
            'date_to'     => $request->input('date_to', '') ?? '',
        ];
        $page    = max(1, (int) ($request->input('page', '1') ?? '1'));
        $perPage = min(200, max(1, (int) ($request->input('per_page', '50') ?? '50')));

        $result = (new AuditLogService())->paginate($filters, $page, $perPage);

        return $this->json([
            'ok'      => true,
            'filters' => $filters,
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }
}
