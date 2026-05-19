<?php

namespace CaddyPanel\PhpVersions;

use CaddyPanel\Core\Database;
use CaddyPanel\Settings\SettingRepository;
use CaddyPanel\System\CommandRunner;

class PhpVersionService
{
    public function __construct(
        private PhpVersionRepository $versions,
        private SettingRepository $settings,
        private CommandRunner $commands,
        private Database $database,
        private string $env = 'local'
    ) {
    }

    public function all(): array
    {
        $rows = $this->versions->all();

        if ($rows !== []) {
            return $rows;
        }

        return [$this->defaultRow()];
    }

    public function refresh(int $userId, string $ipAddress): array
    {
        $detected = $this->detect();

        if ($detected === []) {
            throw new \RuntimeException('No PHP-FPM sockets were detected.');
        }

        $defaultVersion = $this->settings->get('default_php_version', '8.4');
        $hasDefault = false;

        foreach ($detected as &$row) {
            $row['is_default'] = $row['version'] === $defaultVersion ? 1 : 0;
            $hasDefault = $hasDefault || $row['is_default'] === 1;
        }

        unset($row);

        if (!$hasDefault) {
            $detected[0]['is_default'] = 1;
            $this->settings->set('default_php_version', $detected[0]['version']);
            $this->settings->set('default_php_fpm_socket', $detected[0]['fpm_socket']);
        }

        $this->versions->replaceDetected($detected);
        $this->audit($userId, 'php_versions_refresh', 'success', 'Detected ' . count($detected) . ' PHP-FPM version(s).', $ipAddress);

        return $this->all();
    }

    public function setDefault(string $version, int $userId, string $ipAddress): void
    {
        $row = $this->versions->findByVersion($version);

        if (!$row) {
            throw new \InvalidArgumentException('PHP version was not detected: ' . $version);
        }

        $this->versions->setDefault($version);
        $this->settings->set('default_php_version', $row['version']);
        $this->settings->set('default_php_fpm_socket', $row['fpm_socket']);
        $this->audit($userId, 'php_versions_default', 'success', 'Default PHP version set to ' . $version . '.', $ipAddress);
    }

    private function detect(): array
    {
        if ($this->env === 'local') {
            return [$this->defaultRow()];
        }

        $result = $this->commands->run('php-fpm-detect');

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException($result['output']);
        }

        $decoded = json_decode($result['output'], true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid php-fpm-detect output.');
        }

        return array_values(array_filter($decoded, fn ($row): bool => is_array($row) && isset($row['version'], $row['fpm_socket'])));
    }

    private function defaultRow(): array
    {
        return [
            'version' => $this->settings->get('default_php_version', '8.4'),
            'fpm_socket' => $this->settings->get('default_php_fpm_socket', '/run/php/php8.4-fpm.sock'),
            'is_default' => 1,
            'detected_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function audit(int $userId, string $action, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [$userId, $action, 'php_versions', $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
