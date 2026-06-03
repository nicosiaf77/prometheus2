<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;
use Prometheus\Services\ActivityCategoryService;

final class ActivityCategoryController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok' => true,
            'categories' => (new ActivityCategoryService())->all(),
        ]);
    }
}
