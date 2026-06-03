<?php

declare(strict_types=1);

use Prometheus\Controllers\ActivityCategoryController;
use Prometheus\Controllers\AgentController;
use Prometheus\Controllers\AuthController;
use Prometheus\Controllers\AuditLogController;
use Prometheus\Controllers\BackupController;
use Prometheus\Controllers\ControlController;
use Prometheus\Controllers\DashboardController;
use Prometheus\Controllers\EventController;
use Prometheus\Controllers\IntegrityCheckController;
use Prometheus\Controllers\ReportController;
use Prometheus\Controllers\StatisticsController;
use Prometheus\Controllers\UserController;

$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/csrf-token', [AuthController::class, 'csrfToken']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/controls', [ControlController::class, 'index']);
$router->get('/controls/create', [ControlController::class, 'create']);
$router->post('/controls', [ControlController::class, 'store']);
$router->get('/controls/{control}', [ControlController::class, 'show']);
$router->post('/controls/{control}/validate', [ControlController::class, 'validate']);
$router->post('/controls/{control}/annul', [ControlController::class, 'annul']);
$router->get('/events', [EventController::class, 'index']);
$router->post('/events', [EventController::class, 'store']);
$router->get('/activity-categories', [ActivityCategoryController::class, 'index']);
$router->get('/agents', [AgentController::class, 'index']);
$router->post('/agents', [AgentController::class, 'store']);
$router->get('/statistics', [StatisticsController::class, 'index']);
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/controls.csv', [ReportController::class, 'controlsCsv']);
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/audit-logs', [AuditLogController::class, 'index']);
$router->get('/backup', [BackupController::class, 'index']);
$router->post('/backup', [BackupController::class, 'store']);
$router->get('/integrity-check', [IntegrityCheckController::class, 'index']);
$router->post('/integrity-check', [IntegrityCheckController::class, 'store']);
