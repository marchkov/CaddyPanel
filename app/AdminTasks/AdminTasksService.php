<?php

namespace CaddyPanel\AdminTasks;

use CaddyPanel\Core\Database;
use CaddyPanel\System\CommandRunner;

class AdminTasksService
{
    private const ACTIONS = [
        'caddy-validate' => 'Validate Caddy config',
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

    public function services(): array
    {
        $status = $this->systemStatus();
        $services = [
            [
                'service' => 'caddy',
                'label' => 'Caddy',
                'status' => (string) ($status['caddy'] ?? 'unknown'),
                'type' => 'caddy',
            ],
            [
                'service' => 'mariadb',
                'label' => 'MariaDB',
                'status' => (string) ($status['mariadb'] ?? 'unknown'),
                'type' => 'mariadb',
            ],
        ];

        foreach (($status['php_fpm_services'] ?? []) as $phpFpm) {
            if (!is_array($phpFpm) || empty($phpFpm['service'])) {
                continue;
            }

            $services[] = [
                'service' => (string) $phpFpm['service'],
                'label' => strtoupper((string) $phpFpm['service']),
                'status' => (string) ($phpFpm['status'] ?? 'unknown'),
                'type' => 'php-fpm',
            ];
        }

        if (count($services) === 2) {
            $services[] = [
                'service' => '',
                'label' => 'PHP-FPM',
                'status' => (string) ($status['php_fpm'] ?? 'unknown'),
                'type' => 'php-fpm',
            ];
        }

        return $services;
    }

    public function logTargets(): array
    {
        return self::LOG_TARGETS;
    }

    public function controlService(string $service, string $operation, int $userId, string $ipAddress): array
    {
        $service = $this->assertService($service);

        if (!in_array($operation, ['start', 'stop', 'restart', 'reload'], true)) {
            throw new \InvalidArgumentException('Invalid service operation.');
        }

        if ($operation === 'reload' && $service !== 'caddy') {
            throw new \InvalidArgumentException('Reload is only available for Caddy.');
        }

        $result = $this->commands->run('admin-task', [
            'action' => 'service-control',
            'service' => $service,
            'operation' => $operation,
        ]);
        $this->audit($userId, 'admin_task_service_' . $operation, $result, $ipAddress, $service);

        return $result;
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

    private function assertService(string $service): string
    {
        $service = trim($service);

        if (in_array($service, ['caddy', 'mariadb'], true)) {
            return $service;
        }

        return $this->assertPhpFpmService($service);
    }

    private function systemStatus(): array
    {
        $result = $this->commands->run('system-status');

        if ((int) ($result['exit_code'] ?? 1) !== 0) {
            return [
                'caddy' => 'unknown',
                'mariadb' => 'unknown',
                'php_fpm' => 'unknown',
                'php_fpm_services' => [],
            ];
        }

        $decoded = json_decode((string) ($result['output'] ?? ''), true);

        if (!is_array($decoded)) {
            return [
                'caddy' => 'unknown',
                'mariadb' => 'unknown',
                'php_fpm' => 'unknown',
                'php_fpm_services' => [],
            ];
        }

        $decoded['php_fpm_services'] = is_array($decoded['php_fpm_services'] ?? null) ? $decoded['php_fpm_services'] : [];

        return $decoded;
    }

    private function audit(int $userId, string $action, array $result, string $ipAddress, ?string $messagePrefix = null): void
    {
        $message = (string) ($result['output'] ?? '');
        $message = $messagePrefix !== null ? $messagePrefix . ': ' . $message : $message;

        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                'admin_tasks',
                (int) ($result['exit_code'] ?? 1) === 0 ? 'success' : 'failed',
                substr($message, 0, 2000),
                $ipAddress,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
