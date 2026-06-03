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

        $categories = (new ActivityCategoryService())->all();
        $rows = '';

        foreach ($categories as $category) {
            $status = (int) $category['active'] === 1 ? 'Attiva' : 'Disattiva';
            $rows .= '<tr>'
                . '<td>' . $this->e($category['name']) . '</td>'
                . '<td>' . $this->e($category['description'] ?? '') . '</td>'
                . '<td>' . $status . '</td>'
                . '</tr>';
        }

        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Categorie</h1>
                    <p>Categorie principali e secondarie disponibili per i controlli.</p>
                </div>
            </div>
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Nome</th><th>Descrizione</th><th>Stato</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Categorie', $content);
    }
}
