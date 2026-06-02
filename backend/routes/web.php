<?php

declare(strict_types=1);

use Prometheus\Controllers\ActivityCategoryController;
use Prometheus\Controllers\AgentController;
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
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/controls', [ControlController::class, 'index']);
$router->get('/controls/create', [ControlController::class, 'create']);
$router->post('/controls', [ControlController::class, 'store']);
$router->get('/events', [EventController::class, 'index']);
$router->get('/activity-categories', [ActivityCategoryController::class, 'index']);
$router->get('/agents', [AgentController::class, 'index']);
$router->get('/statistics', [StatisticsController::class, 'index']);
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/users', [UserController::class, 'index']);
$router->get('/audit-logs', [AuditLogController::class, 'index']);
$router->get('/backup', [BackupController::class, 'index']);
$router->post('/backup', [BackupController::class, 'store']);
$router->get('/integrity-check', [IntegrityCheckController::class, 'index']);
$router->post('/integrity-check', [IntegrityCheckController::class, 'store']);
