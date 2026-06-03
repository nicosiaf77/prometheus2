<?php

declare(strict_types=1);

namespace Prometheus\Services;

use Prometheus\Core\Database;
use Prometheus\Core\Env;

final class BackupService
{
    public function create(int $userId, string $basePath): array
    {
        $database  = Env::get('DB_DATABASE', 'prometheus2') ?? 'prometheus2';
        $username  = Env::get('DB_USERNAME', 'root') ?? 'root';
        $password  = Env::get('DB_PASSWORD', '') ?? '';
        $host      = Env::get('DB_HOST', 'localhost') ?? 'localhost';
        $backupDir = $basePath . '/storage/backups';

        if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Impossibile creare directory backup.');
        }

        $timestamp = date('Ymd_His');
        $sqlFile   = $backupDir . '/prometheus2_' . $timestamp . '.sql';
        $dumpBin   = $this->resolveDumpBinary();
        $command   = [$dumpBin, '--host=' . $host, '--user=' . $username];

        if ($password !== '') {
            $command[] = '--password=' . $password;
        }

        $command[]    = $database;
        $shellCommand = implode(' ', array_map('escapeshellarg', $command))
            . ' > ' . escapeshellarg($sqlFile);
        exec($shellCommand, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($sqlFile) || filesize($sqlFile) === 0) {
            @unlink($sqlFile);
            throw new \RuntimeException('Backup database non riuscito (exit code: ' . $exitCode . ').');
        }

        // ── Cifratura AES-256-CBC tramite openssl CLI ─────────────────────────
        $encryptBackup = (Env::get('BACKUP_ENCRYPT', 'false') ?? 'false') === 'true';
        $encKey        = Env::get('APP_KEY', '') ?? '';

        if ($encryptBackup && $encKey !== '') {
            $encFile      = $sqlFile . '.enc';
            $encCmd       = 'openssl enc -aes-256-cbc -pbkdf2 -iter 100000'
                . ' -in '  . escapeshellarg($sqlFile)
                . ' -out ' . escapeshellarg($encFile)
                . ' -pass pass:' . escapeshellarg($encKey)
                . ' 2>/dev/null';
            exec($encCmd, $encOut, $encCode);

            if ($encCode !== 0 || !is_file($encFile) || filesize($encFile) === 0) {
                @unlink($encFile);
                // Cifratura fallita: mantieni il file SQL non cifrato con avviso
                $finalFile     = $sqlFile;
                $encryptedFlag = false;
            } else {
                // Rimuove il dump in chiaro, mantiene solo il cifrato
                @unlink($sqlFile);
                $finalFile     = $encFile;
                $encryptedFlag = true;
            }
        } else {
            $finalFile     = $sqlFile;
            $encryptedFlag = false;
        }

        $finalName = basename($finalFile);
        $hash      = hash_file('sha256', $finalFile);

        if ($hash === false) {
            throw new \RuntimeException('Calcolo hash backup non riuscito.');
        }

        Database::connection()->prepare(
            'INSERT INTO backups (user_id, file_name, file_path, file_hash, created_at)
             VALUES (:user_id, :file_name, :file_path, :file_hash, NOW())'
        )->execute([
            'user_id'   => $userId,
            'file_name' => $finalName,
            'file_path' => $finalFile,
            'file_hash' => $hash,
        ]);

        $description = $encryptedFlag
            ? 'Backup cifrato AES-256-CBC creato: ' . $finalName
            : 'Backup creato: ' . $finalName;

        (new AuditService())->record(AuditActions::BACKUP_CREATED, 'backups', null, $description);

        return [
            'file_name' => $finalName,
            'file_path' => $finalFile,
            'file_hash' => $hash,
            'encrypted' => $encryptedFlag,
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
