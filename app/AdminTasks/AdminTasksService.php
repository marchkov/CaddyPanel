<?php

namespace CaddyPanel\AdminTasks;

use CaddyPanel\Core\Database;
use CaddyPanel\System\CommandRunner;

class AdminTasksService
{
    private const ACTIONS = [
        'caddy-validate' => 'Validate Caddy config',
        'caddy-reload' => 'Reload Caddy',
        'php-fpm-restart' => 'Restart PHP-FPM',
        'mariadb-restart' => 'Restart MariaDB',
        'system-status' => 'System status',
    ];

    private const LOG_TARGETS = [
        'caddy' => 'Caddy journal',
        'php-fpm' => 'PHP-FPM journal',
        'mariadb' => 'MariaDB journal',
        'panel-error' => 'Panel error log',
        'backup-scheduler' => 'Backup scheduler log',
        'update-cron' => 'Update cron log',
    ];

    public function __construct(
        private CommandRunner $commands,
        private Database $database
    ) {
    }

    public function actions(): array
    {
        return self::ACTIONS;
    }

    public function logTargets(): array
    {
        return self::LOG_TARGETS;
    }

    public function runAction(string $action, ?string $service, int $userId, string $ipAddress): array
    {
        if (!array_key_exists($action, self::ACTIONS)) {
            throw new \InvalidArgumentException('Unknown admin task.');
        }

        $args = ['action' => $action];

        if ($action === 'php-fpm-restart' && $service !== null && $service !== '') {
            $args['service'] = $this->assertPhpFpmService($service);
        }

        $result = $this->commands->run('admin-task', $args);
        $this->audit($userId, 'admin_task_' . str_replace('-', '_', $action), $result, $ipAddress);

        return $result;
    }

    public function readLog(string $target, int $lines, ?string $service, int $userId, string $ipAddress): array
    {
        if (!array_key_exists($target, self::LOG_TARGETS)) {
            throw new \InvalidArgumentException('Unknown log target.');
        }

        $args = [
            'action' => 'logs',
            'target' => $target,
            'lines' => (string) max(20, min(500, $lines)),
        ];

        if ($target === 'php-fpm' && $service !== null && $service !== '') {
            $args['service'] = $this->assertPhpFpmService($service);
        }

        $result = $this->commands->run('admin-task', $args);
        $this->audit($userId, 'admin_task_log_' . str_replace('-', '_', $target), $result, $ipAddress);

        return $result;
    }

    private function assertPhpFpmService(string $service): string
    {
        $service = trim($service);

        if (preg_match('/^php\d+\.\d+-fpm$/', $service) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP-FPM service.');
        }

        return $service;
    }

    private function audit(int $userId, string $action, array $result, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                'admin_tasks',
                (int) ($result['exit_code'] ?? 1) === 0 ? 'success' : 'failed',
                substr((string) ($result['output'] ?? ''), 0, 2000),
                $ipAddress,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
