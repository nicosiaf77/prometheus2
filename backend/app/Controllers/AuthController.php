<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if ($this->auth()->check()) {
            return $this->redirect('/dashboard');
        }

        $error = Session::get('login_error');
        Session::forget('login_error');
        $errorHtml = is_string($error) ? '<div class="alert alert-danger py-2">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        $csrf = Session::csrfToken();
        $content = <<<HTML
        <main class="login-shell">
            <section class="login-panel">
                <div class="brand login-brand"><span class="brand-mark">P2</span><span>Prometheus2</span></div>
                <h1>Accesso backend</h1>
                <p>Area riservata agli operatori autorizzati.</p>
                {$errorHtml}
                <form method="post" action="/login" class="login-form">
                    <input type="hidden" name="_csrf_token" value="{$csrf}">
                    <label class="form-label" for="identifier">Username o email</label>
                    <input class="form-control" id="identifier" name="identifier" autocomplete="username" required>
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="btn btn-primary btn-sm" type="submit">Accedi</button>
                </form>
            </section>
        </main>
        HTML;

        return $this->view('Login', $content);
    }

    public function login(): Response
    {
        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            Session::put('login_error', 'Sessione non valida. Riprovare.');

            return $this->redirect('/login');
        }

        $identifier = $request->input('identifier', '') ?? '';
        $password = $request->input('password', '') ?? '';

        if ($identifier === '' || $password === '' || !$this->auth()->login($identifier, $password)) {
            Session::put('login_error', 'Credenziali non valide.');

            return $this->redirect('/login');
        }

        return $this->redirect('/dashboard');
    }

    public function logout(): Response
    {
        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->redirect('/dashboard');
        }

        $this->auth()->logout();

        return $this->redirect('/login');
    }
}
