<?php

declare(strict_types=1);

use Prometheus\Core\Database;
use Prometheus\Core\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$options = getopt('', [
    'name:',
    'surname:',
    'email:',
    'username:',
    'password:',
]);

$required = ['name', 'surname', 'email', 'username', 'password'];

foreach ($required as $key) {
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Missing --{$key}\n");
        exit(1);
    }
}

$pdo = Database::connection();
$statement = $pdo->prepare(
    'INSERT INTO users (name, surname, email, username, password, role, active, created_at, updated_at)
     VALUES (:name, :surname, :email, :username, :password, :role, 1, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        surname = VALUES(surname),
        password = VALUES(password),
        role = VALUES(role),
        active = 1,
        updated_at = NOW()'
);

$statement->execute([
    'name' => (string) $options['name'],
    'surname' => (string) $options['surname'],
    'email' => (string) $options['email'],
    'username' => (string) $options['username'],
    'password' => password_hash((string) $options['password'], PASSWORD_DEFAULT),
    'role' => 'amministratore',
]);

echo "Admin user ready: {$options['username']}\n";
