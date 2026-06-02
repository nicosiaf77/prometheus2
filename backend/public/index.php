<?php

declare(strict_types=1);

use Prometheus\Core\Application;
use Prometheus\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$router = new Router();

require dirname(__DIR__) . '/routes/web.php';

$app->run($router);
