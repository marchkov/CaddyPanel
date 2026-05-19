<?php

namespace CaddyPanel\SystemStatus;

use CaddyPanel\System\CommandRunner;

class SystemStatusService
{
    public function __construct(
        private CommandRunner $commands,
        private string $env = 'local'
    ) {
    }

    public function current(): array
    {
        if ($this->env === 'local') {
            return [
                'caddy' => 'local',
                'php_fpm' => 'local',
                'php_fpm_services' => [],
                'mariadb' => 'local',
                'disk' => 'local',
            ];
        }

        $result = $this->commands->run('system-status');

        if ($result['exit_code'] !== 0) {
            return [
                'caddy' => 'unknown',
                'php_fpm' => 'unknown',
                'php_fpm_services' => [],
                'mariadb' => 'unknown',
                'disk' => 'unknown',
                'error' => $result['output'],
            ];
        }

        $decoded = json_decode($result['output'], true);

        if (!is_array($decoded)) {
            return [
                'caddy' => 'unknown',
                'php_fpm' => 'unknown',
                'php_fpm_services' => [],
                'mariadb' => 'unknown',
                'disk' => 'unknown',
                'error' => 'Invalid system-status output.',
            ];
        }

        if (!isset($decoded['php_fpm_services']) || !is_array($decoded['php_fpm_services'])) {
            $decoded['php_fpm_services'] = [];
        }

        return $decoded;
    }
}
