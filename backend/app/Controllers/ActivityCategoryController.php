<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class ActivityCategoryController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->view('Categorie', '<main class="container py-4"><h1>Categorie</h1><p>Categorie principali e secondarie.</p></main>');
    }
}
