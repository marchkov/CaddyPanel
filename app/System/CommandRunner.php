<?php

namespace CaddyPanel\System;

class CommandRunner
{
    private array $allowedCommands = [
        'site-create-dirs',
        'site-delete-files',
        'caddy-apply-site-config',
        'caddy-disable-site',
        'caddy-validate',
        'caddy-reload',
        'db-create',
        'db-delete',
        'db-health',
        'db-backup',
        'db-restore',
        'backup-create',
        'backup-restore',
        'update-check',
        'update-apply',
        'system-status',
        'php-fpm-detect',
        'php-fpm-available',
        'php-fpm-job-start',
        'php-fpm-install',
        'php-fpm-uninstall',
        'php-fpm-mark-manual',
        'admin-task',
        'firewall-task',
    ];

    public function __construct(
        private string $binPath,
        private string $env = 'local'
    ) {
    }

    public function run(string $command, array $args = []): array
    {
        if (!in_array($command, $this->allowedCommands, true)) {
            throw new \InvalidArgumentException('Command is not allowed: ' . $command);
        }

        if ($this->env === 'local') {
            return [
                'exit_code' => 0,
                'output' => 'local mode: skipped ' . $command,
            ];
        }

        $commandPath = realpath($this->binPath . '/' . $command);

        if ($commandPath === false || !is_file($commandPath)) {
            throw new \RuntimeException('Command not found: ' . $command);
        }

        $parts = ['sudo', escapeshellarg($commandPath)];

        foreach ($args as $key => $value) {
            $parts[] = escapeshellarg((string) $key);
            $parts[] = escapeshellarg((string) $value);
        }

        $fullCommand = implode(' ', $parts) . ' 2>&1';
        $lines = [];
        $exitCode = 0;

        exec($fullCommand, $lines, $exitCode);

        return [
            'exit_code' => $exitCode,
            'output' => implode("\n", $lines),
        ];
    }
}
