<?php

declare(strict_types=1);

use Prometheus\Core\Application;
use Prometheus\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

new Application(dirname(__DIR__));

$force = in_array('--force', $argv, true);
$pdo = Database::connection();
$categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM activity_categories')->fetchColumn();

if ($categoryCount > 0 && !$force) {
    echo "SKIP seed: activity_categories contiene gia {$categoryCount} record. Usa --force per forzare.\n";
    exit(0);
}

$seedFiles = glob(dirname(__DIR__) . '/database/seeders/*.sql') ?: [];
sort($seedFiles);

foreach ($seedFiles as $file) {
    $seed = basename($file);
    $sql = file_get_contents($file);

    if (!is_string($sql) || trim($sql) === '') {
        echo "SKIP {$seed} vuoto\n";
        continue;
    }

    try {
        $pdo->exec($sql);
        echo "OK {$seed}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "KO {$seed}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Seed completati.\n";

