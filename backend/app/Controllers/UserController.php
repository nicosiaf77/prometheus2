<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use PDO;
use Prometheus\Core\Controller;
use Prometheus\Core\Database;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Core\Validator;
use Prometheus\Requests\StoreUserRequest;
use Prometheus\Requests\UpdateUserRequest;
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

        return $this->json([
            'ok'    => true,
            'users' => (new UserService())->all(),
        ]);
    }

    public function show(string $user): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        if (!ctype_digit($user)) {
            return $this->error('Utente non trovato.', 404);
        }

        $userData = (new UserService())->find((int) $user);

        if ($userData === null) {
            return $this->error('Utente non trovato.', 404);
        }

        return $this->json(['ok' => true, 'user' => $userData]);
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

        $data = [
            'name'     => $request->input('name', '') ?? '',
            'surname'  => $request->input('surname', '') ?? '',
            'email'    => $request->input('email', '') ?? '',
            'username' => $request->input('username', '') ?? '',
            'password' => $request->input('password', '') ?? '',
            'role'     => $request->input('role', '') ?? '',
        ];

        $errors = (new Validator())->validate($data, (new StoreUserRequest())->rules());

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $service = new UserService();

        if ($service->isEmailTaken($data['email'])) {
            return $this->validationError(['email' => ['Email già in uso.']]);
        }

        if ($service->isUsernameTaken($data['username'])) {
            return $this->validationError(['username' => ['Username già in uso.']]);
        }

        try {
            $userId = $service->create($data);
            (new AuditService())->record(AuditActions::USER_CREATED, 'users', $userId, 'Utente creato: ' . $data['username']);
        } catch (Throwable $exception) {
            return $this->error('Creazione utente non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok'      => true,
            'message' => 'Utente creato correttamente.',
            'user_id' => $userId,
        ], 201);
    }

    public function update(string $user): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($user)) {
            return $this->error('Utente non trovato.', 404);
        }

        $userId = (int) $user;
        $service = new UserService();

        if ($service->find($userId) === null) {
            return $this->error('Utente non trovato.', 404);
        }

        $data = [
            'name'    => $request->input('name', '') ?? '',
            'surname' => $request->input('surname', '') ?? '',
            'email'   => $request->input('email', '') ?? '',
            'role'    => $request->input('role', '') ?? '',
        ];

        $errors = (new Validator())->validate($data, (new UpdateUserRequest())->rules());

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        if ($service->isEmailTaken($data['email'], $userId)) {
            return $this->validationError(['email' => ['Email già in uso da un altro utente.']]);
        }

        try {
            $service->update($userId, $data);
            (new AuditService())->record(AuditActions::USER_UPDATED, 'users', $userId, 'Utente aggiornato: ' . $data['email']);
        } catch (Throwable $exception) {
            return $this->error('Aggiornamento non riuscito: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Utente aggiornato correttamente.']);
    }

    public function deactivate(string $user): Response
    {
        return $this->toggleActive($user, false);
    }

    public function activate(string $user): Response
    {
        return $this->toggleActive($user, true);
    }

    public function changePassword(string $user): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($user)) {
            return $this->error('Utente non trovato.', 404);
        }

        $userId  = (int) $user;
        $service = new UserService();

        if ($service->find($userId) === null) {
            return $this->error('Utente non trovato.', 404);
        }

        $password = trim($request->input('password', '') ?? '');

        if (strlen($password) < 8) {
            return $this->validationError(['password' => ['La password deve contenere almeno 8 caratteri.']]);
        }

        try {
            $service->changePassword($userId, $password);
            (new AuditService())->record(AuditActions::USER_UPDATED, 'users', $userId, 'Password cambiata per utente id ' . $userId);
        } catch (Throwable $exception) {
            return $this->error('Cambio password non riuscito: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Password aggiornata correttamente.']);
    }

    public function profileChangePassword(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        $currentUser = $this->auth()->user();

        if ($currentUser === null) {
            return $this->error('Sessione non valida.', 401);
        }

        $currentPassword = $request->input('current_password', '') ?? '';
        $newPassword      = trim($request->input('password', '') ?? '');

        if ($currentPassword === '') {
            return $this->validationError(['current_password' => ['La password attuale è obbligatoria.']]);
        }

        if (strlen($newPassword) < 8) {
            return $this->validationError(['password' => ['La nuova password deve contenere almeno 8 caratteri.']]);
        }

        // Verifica password attuale
        $service = new UserService();
        $stored  = $service->find((int) $currentUser['id']);

        if ($stored === null) {
            return $this->error('Utente non trovato.', 404);
        }

        // Recupera l'hash dalla tabella (find() non lo espone per sicurezza)
        $stmt = Database::connection()->prepare(
            'SELECT password FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => (int) $currentUser['id']]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash = is_array($row) ? (string) ($row['password'] ?? '') : '';

        if (!password_verify($currentPassword, $hash)) {
            return $this->error('La password attuale non è corretta.', 422);
        }

        try {
            $service->changePassword((int) $currentUser['id'], $newPassword);
            (new AuditService())->record(
                AuditActions::USER_UPDATED,
                'users',
                (int) $currentUser['id'],
                'Cambio password self-service utente ' . ($currentUser['username'] ?? '')
            );
        } catch (Throwable $exception) {
            return $this->error('Cambio password non riuscito: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Password aggiornata correttamente.']);
    }

    private function toggleActive(string $user, bool $active): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($user)) {
            return $this->error('Utente non trovato.', 404);
        }

        $userId = (int) $user;

        // Un amministratore non può disattivare se stesso.
        $currentUser = $this->auth()->user();

        if (!$active && $currentUser !== null && (int) $currentUser['id'] === $userId) {
            return $this->error('Non puoi disattivare il tuo stesso account.', 422);
        }

        $service = new UserService();

        if ($service->find($userId) === null) {
            return $this->error('Utente non trovato.', 404);
        }

        try {
            $service->setActive($userId, $active);
            $action = $active ? 'riattivato' : 'disattivato';
            (new AuditService())->record(AuditActions::USER_UPDATED, 'users', $userId, 'Utente ' . $action . ' id ' . $userId);
        } catch (Throwable $exception) {
            return $this->error('Operazione non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Utente ' . ($active ? 'riattivato' : 'disattivato') . ' correttamente.']);
    }
}
