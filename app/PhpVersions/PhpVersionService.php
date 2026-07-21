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

    public function overview(): array
    {
        $installed = $this->currentInstalled();
        $siteCounts = $this->siteCountsByVersion();
        $defaultVersion = $this->settings->get('default_php_version', '8.4');
        $defaultSocket = $this->settings->get('default_php_fpm_socket', '/run/php/php8.4-fpm.sock');
        $panelVersion = $this->settings->get('panel_php_version', $defaultVersion);
        $panelSocket = $this->settings->get('panel_php_fpm_socket', $defaultSocket);

        foreach ($installed as &$row) {
            $row['site_count'] = $siteCounts[(string) $row['version']] ?? 0;
            $row['is_default'] = ((string) $row['version'] === (string) $defaultVersion || (string) $row['fpm_socket'] === (string) $defaultSocket) ? 1 : 0;
            $row['is_panel_runtime'] = ((string) $row['version'] === (string) $panelVersion || (string) $row['fpm_socket'] === (string) $panelSocket) ? 1 : 0;
            $row['socket_exists'] = array_key_exists('socket_exists', $row) ? (bool) $row['socket_exists'] : $this->socketExists((string) $row['fpm_socket']);
            $row['runtime_status'] = $this->runtimeStatus($row);
        }

        unset($row);

        return [
            'installed' => $installed,
            'available' => $this->available(),
            'configured_missing' => $this->configuredMissing($installed, $siteCounts, $defaultVersion, $defaultSocket, $panelVersion, $panelSocket),
        ];
    }

    public function refresh(int $userId, string $ipAddress): array
    {
        $detected = $this->detect();

        if ($detected === []) {
            throw new \RuntimeException('No PHP-FPM runtimes were detected.');
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

    public function markManual(string $version, int $userId, string $ipAddress): void
    {
        if (preg_match('/^\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP version.');
        }

        if ($this->env === 'local') {
            $this->audit($userId, 'php_versions_mark_manual', 'success', 'Local mode skipped PHP ' . $version . ' manual mark.', $ipAddress);
            return;
        }

        $result = $this->commands->run('php-fpm-mark-manual', ['version' => $version]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException($result['output']);
        }

        $this->audit($userId, 'php_versions_mark_manual', 'success', 'Marked installed PHP ' . $version . ' packages as manually installed.', $ipAddress);
    }

    public function setDefault(string $version, int $userId, string $ipAddress): void
    {
        $row = $this->versions->findByVersion($version);

        if (!$row) {
            $detected = $this->detect();

            foreach ($detected as &$detectedRow) {
                $detectedRow['is_default'] = (string) $detectedRow['version'] === $version ? 1 : 0;

                if ($detectedRow['is_default'] === 1) {
                    $row = $detectedRow;
                }
            }

            unset($detectedRow);

            if (!$row) {
                throw new \InvalidArgumentException('PHP version was not detected: ' . $version);
            }

            $this->versions->replaceDetected($detected);
        } else {
            $this->versions->setDefault($version);
        }

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

    private function currentInstalled(): array
    {
        if ($this->env === 'local') {
            return $this->all();
        }

        try {
            $detected = $this->detect();
        } catch (\Throwable) {
            return $this->all();
        }

        if ($detected === []) {
            return $this->all();
        }

        $stored = [];

        foreach ($this->versions->all() as $row) {
            $stored[(string) $row['version']] = $row;
        }

        foreach ($detected as &$row) {
            $version = (string) $row['version'];
            $row['is_default'] = (int) ($stored[$version]['is_default'] ?? 0);
            $row['detected_at'] = $stored[$version]['detected_at'] ?? date('Y-m-d H:i:s');
        }

        unset($row);

        return $detected;
    }

    private function available(): array
    {
        if ($this->env === 'local') {
            return [
                [
                    'version' => '8.4',
                    'package' => 'php8.4-fpm',
                    'candidate' => 'local',
                ],
            ];
        }

        $result = $this->commands->run('php-fpm-available');

        if ($result['exit_code'] !== 0) {
            return [];
        }

        $decoded = json_decode($result['output'], true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($row): bool => is_array($row) && isset($row['version'], $row['package'])));
    }

    private function siteCountsByVersion(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT php_version, COUNT(*) AS count
             FROM sites
             WHERE deleted_at IS NULL AND php_enabled = 1 AND php_version IS NOT NULL
             GROUP BY php_version'
        );

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['php_version']] = (int) $row['count'];
        }

        return $counts;
    }

    private function configuredMissing(array $installed, array $siteCounts, ?string $defaultVersion, ?string $defaultSocket, ?string $panelVersion, ?string $panelSocket): array
    {
        $installedVersions = [];

        foreach ($installed as $row) {
            $installedVersions[(string) $row['version']] = true;
        }

        $missing = [];
        $configured = array_unique(array_filter(array_merge(array_keys($siteCounts), [$defaultVersion, $panelVersion])));

        foreach ($configured as $version) {
            if (isset($installedVersions[(string) $version])) {
                continue;
            }

            $socket = (string) $this->socketForVersion((string) $version, $defaultVersion, $defaultSocket, $panelVersion, $panelSocket);

            $missing[] = [
                'version' => (string) $version,
                'fpm_socket' => $socket,
                'site_count' => $siteCounts[(string) $version] ?? 0,
                'is_default' => (string) $version === (string) $defaultVersion ? 1 : 0,
                'is_panel_runtime' => (string) $version === (string) $panelVersion ? 1 : 0,
                'runtime_status' => 'missing',
            ];
        }

        return $missing;
    }

    private function socketForVersion(string $version, ?string $defaultVersion, ?string $defaultSocket, ?string $panelVersion, ?string $panelSocket): string
    {
        if ($version === (string) $panelVersion && $panelSocket !== null && $panelSocket !== '') {
            return $panelSocket;
        }

        if ($version === (string) $defaultVersion && $defaultSocket !== null && $defaultSocket !== '') {
            return $defaultSocket;
        }

        return '/run/php/php' . $version . '-fpm.sock';
    }

    private function socketExists(string $socket): bool
    {
        return $this->env === 'local' || $socket === '' || is_file($socket) || file_exists($socket);
    }

    private function runtimeStatus(array $row): string
    {
        if (empty($row['socket_exists'])) {
            return 'missing';
        }

        if (($row['service_status'] ?? '') === 'inactive' || ($row['service_status'] ?? '') === 'failed') {
            return 'inactive';
        }

        return 'active';
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
