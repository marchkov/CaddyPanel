<?php

namespace CaddyPanel\Databases;

use CaddyPanel\System\CommandRunner;

class DatabaseProvisioner
{
    public function __construct(
        private CommandRunner $commands,
        private string $backupPath,
        private string $workingPath,
        private string $env = 'local'
    ) {
    }

    public function create(string $name, string $username, string $password): string
    {
        $result = $this->commands->run('db-create', [
            'name' => $name,
            'username' => $username,
            'password' => $password,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to create database: ' . $result['output']);
        }

        return $result['output'];
    }

    public function delete(string $name, string $username): string
    {
        $result = $this->commands->run('db-delete', [
            'name' => $name,
            'username' => $username,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to delete database: ' . $result['output']);
        }

        return $result['output'];
    }

    public function health(string $name): string
    {
        $result = $this->commands->run('db-health', [
            'name' => $name,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Database health task failed: ' . $result['output']);
        }

        return $result['output'];
    }

    public function backup(string $name): string
    {
        if ($this->env === 'local') {
            return $this->createLocalBackup($name);
        }

        $result = $this->commands->run('db-backup', [
            'name' => $name,
            'backup_dir' => $this->databaseBackupPath(),
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Database backup failed: ' . $result['output']);
        }

        $file = trim($result['output']);

        if (!is_file($file)) {
            throw new \RuntimeException('Database backup file was not created.');
        }

        return $file;
    }

    public function restore(string $name, string $archivePath): string
    {
        if ($this->env === 'local') {
            return 'local mode: restore simulation completed for ' . $name . ' using ' . $archivePath;
        }

        $result = $this->commands->run('db-restore', [
            'name' => $name,
            'archive_path' => $archivePath,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Database restore failed: ' . $result['output']);
        }

        return $result['output'];
    }

    public function uploadPath(string $name, string $originalName): string
    {
        $directory = $this->databaseWorkingPath() . '/uploads';

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', basename($originalName)) ?: 'database-backup.sql';

        return $directory . '/' . $name . '-' . date('YmdHis') . '-' . $safeName;
    }

    private function createLocalBackup(string $name): string
    {
        $directory = $this->databaseWorkingPath();

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $file = $directory . '/' . $name . '-' . date('YmdHis') . '.sql.gz';
        $content = "-- Local CaddyPanel database backup placeholder\n-- Database: " . $name . "\n";
        file_put_contents($file, gzencode($content));

        return $file;
    }

    private function databaseBackupPath(): string
    {
        return rtrim($this->backupPath, '/\\') . '/databases';
    }

    private function databaseWorkingPath(): string
    {
        return rtrim($this->workingPath, '/\\') . '/databases';
    }
}
