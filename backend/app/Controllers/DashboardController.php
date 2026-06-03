<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        $user = $this->auth()->user();
        $content = file_get_contents(dirname(__DIR__, 2) . '/resources/dashboard.html') ?: '<main>Prometheus2</main>';
        $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')) ?: ($user['username'] ?? 'Utente');
        $content = str_replace(
            ['{{USER_NAME}}', '{{CSRF_TOKEN}}'],
            [htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'), \Prometheus\Core\Session::csrfToken()],
            $content
        );

        return $this->view('Dashboard', $content);
    }
}
