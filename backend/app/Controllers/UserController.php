<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\AuditActions;
use Prometheus\Services\AuditService;
use Prometheus\Services\UserService;
use Throwable;

final class UserController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $users = (new UserService())->all();
        $flash = $this->flash();
        $flashHtml = $flash !== null ? '<div class="alert alert-info py-2">' . $this->e($flash) . '</div>' : '';
        $rows = '';

        foreach ($users as $user) {
            $rows .= '<tr>'
                . '<td>' . $this->e($user['surname']) . '</td>'
                . '<td>' . $this->e($user['name']) . '</td>'
                . '<td>' . $this->e($user['username']) . '</td>'
                . '<td>' . $this->e($user['email']) . '</td>'
                . '<td>' . $this->e($user['role']) . '</td>'
                . '<td>' . ((int) $user['active'] === 1 ? 'Attivo' : 'Disattivo') . '</td>'
                . '<td>' . $this->e($user['last_login_at'] ?? '') . '</td>'
                . '</tr>';
        }

        $csrf = $this->csrfField();
        $content = <<<HTML
        <main class="container py-4">
            <div class="page-title">
                <div>
                    <h1>Utenti</h1>
                    <p>Gestione utenti riservata agli amministratori.</p>
                </div>
            </div>
            {$flashHtml}
            <div class="panel mb-3">
                <form method="post" action="/users" class="filter-form">
                    {$csrf}
                    <input class="form-control form-control-sm" name="surname" placeholder="Cognome" required>
                    <input class="form-control form-control-sm" name="name" placeholder="Nome" required>
                    <input class="form-control form-control-sm" name="username" placeholder="Username" required>
                    <input class="form-control form-control-sm" type="email" name="email" placeholder="Email" required>
                    <input class="form-control form-control-sm" type="password" name="password" placeholder="Password temporanea" required>
                    <select class="form-select form-select-sm" name="role" required>
                        <option value="amministratore">Amministratore</option>
                        <option value="responsabile_ufficio">Responsabile ufficio</option>
                        <option value="operatore">Operatore</option>
                        <option value="lettore">Lettore</option>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Crea utente</button>
                </form>
            </div>
            <div class="panel">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Cognome</th><th>Nome</th><th>Username</th><th>Email</th><th>Ruolo</th><th>Stato</th><th>Ultimo login</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </main>
        HTML;

        return $this->view('Utenti', $content);
    }

    public function store(): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return new Response('Sessione non valida.', 419);
        }

        $data = [
            'name' => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'email' => $request->input('email', '') ?? '',
            'username' => $request->input('username', '') ?? '',
            'password' => $request->input('password', '') ?? '',
            'role' => $request->input('role', '') ?? '',
        ];

        if ($this->invalid($data)) {
            $this->flash('Tutti i campi utente sono obbligatori e il ruolo deve essere valido.');

            return $this->redirect('/users');
        }

        try {
            $userId = (new UserService())->create($data);
            (new AuditService())->record(AuditActions::USER_CREATED, 'users', $userId, 'Utente creato: ' . $data['username']);
            $this->flash('Utente creato correttamente.');
        } catch (Throwable $exception) {
            $this->flash('Creazione utente non riuscita: ' . $exception->getMessage());
        }

        return $this->redirect('/users');
    }

    private function invalid(array $data): bool
    {
        $roles = ['amministratore', 'responsabile_ufficio', 'operatore', 'lettore'];

        return $data['name'] === ''
            || $data['surname'] === ''
            || $data['email'] === ''
            || $data['username'] === ''
            || $data['password'] === ''
            || !in_array($data['role'], $roles, true);
    }
}
