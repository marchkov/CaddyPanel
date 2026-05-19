<?php

namespace CaddyPanel\Restore;

use CaddyPanel\System\CommandRunner;

class RestoreProvisioner
{
    public function __construct(
        private CommandRunner $commands,
        private string $restorePath,
        private string $env = 'local'
    ) {
    }

    public function inspect(?string $backupFile): array
    {
        if (!$backupFile || !is_file($backupFile)) {
            return [
                'exists' => false,
                'manifest' => null,
                'message' => 'Backup file is not available on this machine.',
            ];
        }

        if (str_ends_with($backupFile, '.manifest.json')) {
            $manifest = json_decode((string) file_get_contents($backupFile), true);

            return [
                'exists' => true,
                'manifest' => is_array($manifest) ? $manifest : null,
                'message' => 'Local manifest backup.',
            ];
        }

        return [
            'exists' => true,
            'manifest' => null,
            'message' => 'Archive inspection is available during restore staging.',
        ];
    }

    public function restore(array $backup, array $selected, ?array $targetDatabase = null): array
    {
        $backupFile = (string) ($backup['backup_file'] ?? '');
        $restoreDir = rtrim($this->restorePath, '/\\') . '/restore-' . date('YmdHis');

        if ($this->env === 'local') {
            if (!is_dir($restoreDir)) {
                mkdir($restoreDir, 0775, true);
            }

            if (is_file($backupFile) && str_ends_with($backupFile, '.manifest.json')) {
                copy($backupFile, $restoreDir . '/manifest.json');
            }

            return [
                'directory' => $restoreDir,
                'message' => 'Local restore simulation completed. No files were overwritten.',
            ];
        }

        $args = [
            'backup_file' => $backupFile,
            'restore_dir' => $restoreDir,
            'domain' => $backup['domain'],
            'root_path' => $backup['root_path'],
            'config_path' => $backup['caddy_config_path'],
            'restore_files' => $selected['files'] ? '1' : '0',
            'restore_database' => $selected['database'] ? '1' : '0',
            'restore_host_config' => $selected['host_config'] ? '1' : '0',
        ];

        if ($targetDatabase !== null) {
            $args['database_name'] = $targetDatabase['name'];
        }

        $result = $this->commands->run('backup-restore', $args);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Restore helper failed: ' . $result['output']);
        }

        return [
            'directory' => $restoreDir,
            'message' => $result['output'],
        ];
    }
}
