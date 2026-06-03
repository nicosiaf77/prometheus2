<?php

declare(strict_types=1);

namespace Prometheus\Controllers;

use Prometheus\Core\Controller;
use Prometheus\Core\Request;
use Prometheus\Core\Response;
use Prometheus\Core\Session;
use Prometheus\Services\ActivityCategoryService;
use Throwable;

final class ActivityCategoryController extends Controller
{
    public function index(): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        return $this->json([
            'ok'         => true,
            'categories' => (new ActivityCategoryService())->all(),
        ]);
    }

    public function show(string $category): Response
    {
        if ($response = $this->requireAuth()) {
            return $response;
        }

        if (!ctype_digit($category)) {
            return $this->error('Categoria non trovata.', 404);
        }

        $data = (new ActivityCategoryService())->find((int) $category);

        if ($data === null) {
            return $this->error('Categoria non trovata.', 404);
        }

        return $this->json(['ok' => true, 'category' => $data]);
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

        $name        = trim($request->input('name', '') ?? '');
        $description = $request->input('description', '') ?? '';

        if ($name === '') {
            return $this->validationError(['name' => ['Il nome della categoria è obbligatorio.']]);
        }

        $service = new ActivityCategoryService();

        if ($service->isNameTaken($name)) {
            return $this->validationError(['name' => ['Categoria già esistente con questo nome.']]);
        }

        try {
            $id = $service->create($name, $description !== '' ? $description : null);
        } catch (Throwable $exception) {
            return $this->error('Creazione categoria non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json([
            'ok'          => true,
            'message'     => 'Categoria creata correttamente.',
            'category_id' => $id,
        ], 201);
    }

    public function update(string $category): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($category)) {
            return $this->error('Categoria non trovata.', 404);
        }

        $categoryId = (int) $category;
        $service    = new ActivityCategoryService();

        if ($service->find($categoryId) === null) {
            return $this->error('Categoria non trovata.', 404);
        }

        $name        = trim($request->input('name', '') ?? '');
        $description = $request->input('description', '') ?? '';

        if ($name === '') {
            return $this->validationError(['name' => ['Il nome della categoria è obbligatorio.']]);
        }

        if ($service->isNameTaken($name, $categoryId)) {
            return $this->validationError(['name' => ['Nome già usato da un\'altra categoria.']]);
        }

        try {
            $service->update($categoryId, $name, $description !== '' ? $description : null);
        } catch (Throwable $exception) {
            return $this->error('Aggiornamento non riuscito: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Categoria aggiornata correttamente.']);
    }

    public function deactivate(string $category): Response
    {
        if ($response = $this->requireRoles(['amministratore'])) {
            return $response;
        }

        $request = new Request();

        if (!Session::validateCsrf($request->input('_csrf_token'))) {
            return $this->error('Sessione non valida.', 419);
        }

        if (!ctype_digit($category)) {
            return $this->error('Categoria non trovata.', 404);
        }

        $categoryId = (int) $category;
        $service    = new ActivityCategoryService();

        if ($service->find($categoryId) === null) {
            return $this->error('Categoria non trovata.', 404);
        }

        try {
            $service->setActive($categoryId, false);
        } catch (Throwable $exception) {
            return $this->error('Disattivazione non riuscita: ' . $exception->getMessage(), 500);
        }

        return $this->json(['ok' => true, 'message' => 'Categoria disattivata correttamente.']);
    }
}
