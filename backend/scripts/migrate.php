<?php

declare(strict_types=1);

use Prometheus\Core\Application;
use Prometheus\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

new Application(dirname(__DIR__));

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at DATETIME NOT NULL
    )'
);

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $migration = basename($file);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = :migration');
    $statement->execute(['migration' => $migration]);

    if ((int) $statement->fetchColumn() > 0) {
        echo "SKIP {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);

    if (!is_string($sql) || trim($sql) === '') {
        echo "SKIP {$migration} vuota\n";
        continue;
    }

    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare('INSERT INTO migrations (migration, executed_at) VALUES (:migration, NOW())');
        $insert->execute(['migration' => $migration]);
        echo "OK {$migration}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "KO {$migration}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Migrazioni completate.\n";
