<?php

namespace CaddyPanel\Backups;

use CaddyPanel\System\CommandRunner;

class BackupProvisioner
{
    public function __construct(
        private CommandRunner $commands,
        private string $backupPath,
        private string $env = 'local'
    ) {
    }

    public function create(array $site, ?array $database = null, array $options = []): array
    {
        $options = $this->normalizeOptions($options);

        if ($this->env === 'local') {
            return $this->createLocalManifest($site, $database, $options);
        }

        $args = [
            'domain' => $site['domain'],
            'root_path' => $site['root_path'],
            'config_path' => $site['caddy_config_path'],
            'backup_dir' => $this->backupPath,
        ];

        if ($database !== null) {
            $args['database_name'] = $database['name'];
        }

        $args['include_files'] = $options['include_files'] ? '1' : '0';
        $args['include_database'] = $options['include_database'] ? '1' : '0';
        $args['include_caddy_config'] = $options['include_caddy_config'] ? '1' : '0';

        $result = $this->commands->run('backup-create', $args);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Backup helper failed: ' . $result['output']);
        }

        $file = trim($result['output']);

        return [
            'file' => $file,
            'size' => is_file($file) ? filesize($file) : null,
            'message' => 'Created backup archive.',
        ];
    }

    private function createLocalManifest(array $site, ?array $database = null, array $options = []): array
    {
        $directory = rtrim($this->backupPath, '/\\');

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $date = date('Ymd');
        $number = 1;

        do {
            $file = sprintf('%s/%s-%s_%02d.manifest.json', $directory, $site['domain'], $date, $number);
            $number++;
        } while (is_file($file));

        $manifest = [
            'domain' => $site['domain'],
            'created_at' => date(DATE_ATOM),
            'mode' => 'local',
            'contains' => [
                'files' => $options['include_files'],
                'database' => $options['include_database'] && $database !== null,
                'caddy' => $options['include_caddy_config'],
                'metadata' => true,
            ],
            'site' => [
                'id' => (int) $site['id'],
                'type' => $site['type'],
                'root_path' => $site['root_path'],
                'caddy_config_path' => $site['caddy_config_path'],
            ],
            'database' => $database === null ? null : [
                'id' => (int) $database['id'],
                'name' => $database['name'],
                'username' => $database['username'],
                'host' => $database['host'],
            ],
        ];

        file_put_contents($file, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'file' => $file,
            'size' => filesize($file),
            'message' => 'Created local backup manifest.',
        ];
    }

    private function normalizeOptions(array $options): array
    {
        return array_merge([
            'include_files' => true,
            'include_database' => true,
            'include_caddy_config' => true,
        ], $options);
    }
}
