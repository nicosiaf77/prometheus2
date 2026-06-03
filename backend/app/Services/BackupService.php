<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\Env;

final class BackupService
{
    public function create(int $userId, string $basePath): array
    {
        $database = Env::get('DB_DATABASE', 'prometheus2') ?? 'prometheus2';
        $username = Env::get('DB_USERNAME', 'root') ?? 'root';
        $password = Env::get('DB_PASSWORD', '') ?? '';
        $host = Env::get('DB_HOST', 'localhost') ?? 'localhost';
        $backupDir = $basePath . '/storage/backups';

        if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Impossibile creare directory backup.');
        }

        $fileName = 'prometheus2_' . date('Ymd_His') . '.sql';
        $filePath = $backupDir . '/' . $fileName;
        $dumpBin = $this->resolveDumpBinary();
        $command = [
            $dumpBin,
            '--host=' . $host,
            '--user=' . $username,
        ];

        if ($password !== '') {
            $command[] = '--password=' . $password;
        }

        $command[] = $database;
        $shellCommand = implode(' ', array_map('escapeshellarg', $command)) . ' > ' . escapeshellarg($filePath);
        exec($shellCommand, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($filePath) || filesize($filePath) === 0) {
            @unlink($filePath);
            throw new \RuntimeException('Backup database non riuscito (exit code: ' . $exitCode . ').');
        }

        $hash = hash_file('sha256', $filePath);

        if ($hash === false) {
            throw new \RuntimeException('Calcolo hash backup non riuscito.');
        }

        Database::connection()->prepare(
            'INSERT INTO backups (user_id, file_name, file_path, file_hash, created_at)
             VALUES (:user_id, :file_name, :file_path, :file_hash, NOW())'
        )->execute([
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_hash' => $hash,
        ]);

        (new AuditService())->record(AuditActions::BACKUP_CREATED, 'backups', null, 'Backup creato: ' . $fileName);

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_hash' => $hash,
        ];
    }

    public function latest(): array
    {
        $statement = Database::connection()->query(
            'SELECT backups.file_name, backups.file_hash, backups.created_at, users.username
             FROM backups
             LEFT JOIN users ON users.id = backups.user_id
             ORDER BY backups.created_at DESC
             LIMIT 10'
        );

        return $statement->fetchAll();
    }

    private function resolveDumpBinary(): string
    {
        $configured = trim((string) (Env::get('BACKUP_DUMP_COMMAND', '') ?? ''));

        if ($configured !== '') {
            if (!is_executable($configured)) {
                throw new \RuntimeException('BACKUP_DUMP_COMMAND non eseguibile: ' . $configured);
            }

            return $configured;
        }

        $candidates = ['mariadb-dump', 'mysqldump'];

        foreach ($candidates as $candidate) {
            $resolved = trim((string) shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));

            if ($resolved !== '') {
                return $resolved;
            }
        }

        throw new \RuntimeException('Nessun dump binary trovato. Imposta BACKUP_DUMP_COMMAND nel file .env.');
    }
}
