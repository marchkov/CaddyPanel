<?php

namespace CaddyPanel\PhpVersions;

use CaddyPanel\Core\Database;
use CaddyPanel\Settings\SettingRepository;
use CaddyPanel\System\CommandRunner;

class PhpVersionService
{
    private const CACHE_TTL_SECONDS = 604800;
    private const CACHE_KEY = 'php_versions_system_cache';

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
        $snapshot = $this->systemSnapshot();
        $installed = $this->currentInstalled($snapshot);
        $siteCounts = $this->siteCountsByVersion();
        $defaultVersion = $this->settings->get('default_php_version', '8.4');
        $defaultSocket = $this->settings->get('default_php_fpm_socket', '/run/php/php8.4-fpm.sock');
        $panelVersion = $this->settings->get('panel_php_version', $defaultVersion);
        $panelSocket = $this->settings->get('panel_php_fpm_socket', $defaultSocket);
        $jobsByVersion = $this->versions->latestJobsByVersion();

        foreach ($installed as &$row) {
            $row['site_count'] = $siteCounts[(string) $row['version']] ?? 0;
            $row['is_default'] = ((string) $row['version'] === (string) $defaultVersion || (string) $row['fpm_socket'] === (string) $defaultSocket) ? 1 : 0;
            $row['is_panel_runtime'] = ((string) $row['version'] === (string) $panelVersion || (string) $row['fpm_socket'] === (string) $panelSocket) ? 1 : 0;
            $row['socket_exists'] = array_key_exists('socket_exists', $row) ? (bool) $row['socket_exists'] : $this->socketExists((string) $row['fpm_socket']);
            $row['runtime_status'] = $this->runtimeStatus($row);
            $row['job'] = $jobsByVersion[(string) $row['version']] ?? null;
        }

        unset($row);

        return [
            'installed' => $installed,
            'available' => $this->availableWithInstallState($installed, $snapshot['available'], $jobsByVersion),
            'configured_missing' => $this->configuredMissing($installed, $siteCounts, $defaultVersion, $defaultSocket, $panelVersion, $panelSocket),
            'jobs' => $this->versions->recentJobs(),
        ];
    }

    public function refresh(int $userId, string $ipAddress): array
    {
        $snapshot = $this->systemSnapshot(true);
        $detected = $snapshot['installed'];

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

    public function install(string $version, int $userId, string $ipAddress): int
    {
        if (preg_match('/^\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP version.');
        }

        if (!$this->isAvailable($version)) {
            throw new \InvalidArgumentException('PHP version is not available from the configured APT repositories.');
        }

        if ($this->env === 'local') {
            $this->audit($userId, 'php_versions_install', 'success', 'Local mode skipped PHP ' . $version . ' install.', $ipAddress);
            return 0;
        }

        return $this->queueJob('install', $version, $userId, $ipAddress);
    }

    public function uninstall(string $version, int $userId, string $ipAddress): int
    {
        if (preg_match('/^\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP version.');
        }

        $overview = $this->overview();
        $target = null;

        foreach ($overview['installed'] as $row) {
            if ((string) $row['version'] === $version) {
                $target = $row;
                break;
            }
        }

        if (!$target) {
            throw new \InvalidArgumentException('PHP version is not installed: ' . $version);
        }

        if ((int) ($target['site_count'] ?? 0) > 0) {
            throw new \InvalidArgumentException('Cannot uninstall PHP ' . $version . ' because active sites are pinned to it.');
        }

        if ((int) ($target['is_default'] ?? 0) === 1) {
            throw new \InvalidArgumentException('Cannot uninstall PHP ' . $version . ' because it is the default PHP version.');
        }

        if ((int) ($target['is_panel_runtime'] ?? 0) === 1) {
            throw new \InvalidArgumentException('Cannot uninstall PHP ' . $version . ' because it is used by the panel.');
        }

        if ($this->env === 'local') {
            $this->audit($userId, 'php_versions_uninstall', 'success', 'Local mode skipped PHP ' . $version . ' uninstall.', $ipAddress);
            return 0;
        }

        return $this->queueJob('uninstall', $version, $userId, $ipAddress);
    }

    public function jobs(): array
    {
        return $this->versions->recentJobs();
    }

    private function queueJob(string $action, string $version, int $userId, string $ipAddress): int
    {
        $active = $this->versions->findActiveJobForVersion($version);

        if ($active) {
            throw new \RuntimeException('PHP ' . $version . ' already has a running job.');
        }

        $jobId = $this->versions->createJob($action, $version, $userId, $ipAddress);
        $result = $this->commands->run('php-fpm-job-start', ['job_id' => (string) $jobId]);

        if ($result['exit_code'] !== 0) {
            $this->versions->failJobStart($jobId, $result['output']);
            throw new \RuntimeException($result['output']);
        }

        $this->audit($userId, 'php_versions_' . $action, 'queued', 'Queued PHP ' . $version . ' ' . $action . ' job #' . $jobId . '.', $ipAddress);

        return $jobId;
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

    private function currentInstalled(array $snapshot): array
    {
        if ($this->env === 'local') {
            return $this->all();
        }

        $detected = $snapshot['installed'];
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

    private function systemSnapshot(bool $force = false): array
    {
        if ($this->env === 'local') {
            return [
                'installed' => $this->all(),
                'available' => [
                    [
                        'version' => '8.4',
                        'package' => 'php8.4-fpm',
                        'candidate' => 'local',
                    ],
                ],
            ];
        }

        if (!$force) {
            $cached = $this->cachedSystemSnapshot();

            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $snapshot = [
                'installed' => $this->detect(),
                'available' => $this->availableLive(),
            ];
            $this->cacheSystemSnapshot($snapshot);
            return $snapshot;
        } catch (\Throwable) {
            $cached = $this->cachedSystemSnapshot(false);

            if ($cached !== null) {
                return $cached;
            }

            return [
                'installed' => $this->all(),
                'available' => [],
            ];
        }
    }

    private function cachedSystemSnapshot(bool $requireFresh = true): ?array
    {
        $raw = $this->settings->get(self::CACHE_KEY);

        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded['checked_at'], $decoded['installed'], $decoded['available'])) {
            return null;
        }

        if (!is_array($decoded['installed']) || !is_array($decoded['available'])) {
            return null;
        }

        $checkedAt = strtotime((string) $decoded['checked_at']);

        if ($checkedAt === false) {
            return null;
        }

        if ($requireFresh && $checkedAt < time() - self::CACHE_TTL_SECONDS) {
            return null;
        }

        return [
            'installed' => $decoded['installed'],
            'available' => $decoded['available'],
        ];
    }

    private function cacheSystemSnapshot(array $snapshot): void
    {
        $this->settings->set(self::CACHE_KEY, json_encode([
            'checked_at' => date('Y-m-d H:i:s'),
            'installed' => $snapshot['installed'] ?? [],
            'available' => $snapshot['available'] ?? [],
        ], JSON_UNESCAPED_SLASHES));
    }

    private function availableLive(): array
    {
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

    private function availableWithInstallState(array $installed, array $available, array $jobsByVersion): array
    {
        $installedByVersion = [];

        foreach ($installed as $row) {
            $installedByVersion[(string) $row['version']] = $row;
        }

        foreach ($available as &$row) {
            $version = (string) $row['version'];
            $installedRow = $installedByVersion[$version] ?? null;
            $row['installed'] = $installedRow !== null ? 1 : 0;
            $row['site_count'] = (int) ($installedRow['site_count'] ?? 0);
            $row['is_default'] = (int) ($installedRow['is_default'] ?? 0);
            $row['is_panel_runtime'] = (int) ($installedRow['is_panel_runtime'] ?? 0);
            $row['can_uninstall'] = $row['installed'] === 1
                && $row['site_count'] === 0
                && $row['is_default'] === 0
                && $row['is_panel_runtime'] === 0;
            $row['job'] = $jobsByVersion[$version] ?? null;
        }

        unset($row);

        return $available;
    }

    private function isAvailable(string $version, bool $forceRefresh = false): bool
    {
        foreach ($this->systemSnapshot($forceRefresh)['available'] as $row) {
            if ((string) $row['version'] === $version) {
                return true;
            }
        }

        return false;
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
