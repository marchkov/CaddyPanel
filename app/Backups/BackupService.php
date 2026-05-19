<?php

namespace CaddyPanel\Backups;

use CaddyPanel\Core\Database;
use CaddyPanel\Databases\DatabaseRepository;
use CaddyPanel\Sites\SiteRepository;

class BackupService
{
    public function __construct(
        private BackupRepository $backups,
        private SiteRepository $sites,
        private DatabaseRepository $databases,
        private BackupProvisioner $provisioner,
        private Database $database
    ) {
    }

    public function all(): array
    {
        return $this->backups->all();
    }

    public function queueForSite(int $siteId, int $userId, string $ipAddress, array $options = []): int
    {
        $site = $this->sites->find($siteId);

        if (!$site || $site['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Site not found.');
        }

        $options = $this->normalizeOptions($options);
        $backupId = $this->backups->create([
            'site_id' => $siteId,
            'status' => 'queued',
            'backup_file' => null,
            'file_size' => null,
            'message' => 'Backup is queued.',
            'include_files' => $options['include_files'],
            'include_database' => $options['include_database'],
            'include_caddy_config' => $options['include_caddy_config'],
            'started_at' => date('Y-m-d H:i:s'),
            'completed_at' => null,
            'created_by_user_id' => $userId,
        ]);

        $this->audit($userId, 'backup_queue', $backupId, 'success', 'Queued backup for ' . $site['domain'] . '.', $ipAddress);

        return $backupId;
    }

    public function processQueued(int $limit = 0): array
    {
        $processed = [];

        do {
            $queued = $this->backups->nextQueued();

            if (!$queued) {
                break;
            }

            $processed[] = $this->processQueuedBackup($queued);
        } while ($limit <= 0 || count($processed) < $limit);

        return $processed;
    }

    public function createForSite(int $siteId, ?int $userId, string $ipAddress, array $options = []): int
    {
        $site = $this->sites->find($siteId);

        if (!$site || $site['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Site not found.');
        }

        $startedAt = date('Y-m-d H:i:s');
        $options = array_merge([
            'include_files' => true,
            'include_database' => true,
            'include_caddy_config' => true,
        ], $options);

        try {
            $linkedDatabase = !empty($options['include_database'])
                ? $this->databases->findActiveBySiteId($siteId)
                : null;
            $result = $this->provisioner->create($site, $linkedDatabase, $options);
            $backupId = $this->backups->create([
                'site_id' => $siteId,
                'status' => 'success',
                'backup_file' => $result['file'],
                'file_size' => $result['size'],
                'message' => $result['message'],
                'include_files' => $options['include_files'],
                'include_database' => $options['include_database'],
                'include_caddy_config' => $options['include_caddy_config'],
                'started_at' => $startedAt,
                'completed_at' => date('Y-m-d H:i:s'),
                'created_by_user_id' => $userId,
            ]);
            $this->audit($userId, 'backup_create', $backupId, 'success', $result['message'], $ipAddress);

            return $backupId;
        } catch (\Throwable $exception) {
            $backupId = $this->backups->create([
                'site_id' => $siteId,
                'status' => 'failed',
                'backup_file' => null,
                'file_size' => null,
                'message' => $exception->getMessage(),
                'include_files' => $options['include_files'],
                'include_database' => $options['include_database'],
                'include_caddy_config' => $options['include_caddy_config'],
                'started_at' => $startedAt,
                'completed_at' => date('Y-m-d H:i:s'),
                'created_by_user_id' => $userId,
            ]);
            $this->audit($userId, 'backup_create', $backupId, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }
    }

    public function downloadable(int $backupId): array
    {
        $backup = $this->backups->find($backupId);

        if (!$backup) {
            throw new \InvalidArgumentException('Backup not found.');
        }

        if (($backup['status'] ?? '') !== 'success') {
            throw new \InvalidArgumentException('Only successful backups can be downloaded.');
        }

        $file = (string) ($backup['backup_file'] ?? '');

        if ($file === '' || !is_file($file)) {
            throw new \InvalidArgumentException('Backup file is not available.');
        }

        return [
            'backup' => $backup,
            'file' => $file,
            'name' => basename($file),
            'size' => filesize($file),
        ];
    }

    private function processQueuedBackup(array $backup): array
    {
        $backupId = (int) $backup['id'];
        $this->backups->markRunning($backupId);

        $options = $this->normalizeOptions([
            'include_files' => (int) ($backup['include_files'] ?? 1) === 1,
            'include_database' => (int) ($backup['include_database'] ?? 1) === 1,
            'include_caddy_config' => (int) ($backup['include_caddy_config'] ?? 1) === 1,
        ]);

        try {
            $linkedDatabase = $options['include_database']
                ? $this->databases->findActiveBySiteId((int) $backup['site_id'])
                : null;
            $result = $this->provisioner->create($backup, $linkedDatabase, $options);
            $this->backups->markCompleted($backupId, 'success', $result['file'], $result['size'], $result['message']);
            $this->audit($backup['created_by_user_id'] ? (int) $backup['created_by_user_id'] : null, 'backup_create', $backupId, 'success', $result['message'], 'cli');

            return [
                'backup_id' => $backupId,
                'site_id' => (int) $backup['site_id'],
                'status' => 'success',
                'file' => $result['file'],
            ];
        } catch (\Throwable $exception) {
            $this->backups->markCompleted($backupId, 'failed', null, null, $exception->getMessage());
            $this->audit($backup['created_by_user_id'] ? (int) $backup['created_by_user_id'] : null, 'backup_create', $backupId, 'failed', $exception->getMessage(), 'cli');

            return [
                'backup_id' => $backupId,
                'site_id' => (int) $backup['site_id'],
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function normalizeOptions(array $options): array
    {
        return array_merge([
            'include_files' => true,
            'include_database' => true,
            'include_caddy_config' => true,
        ], $options);
    }

    private function audit(?int $userId, string $action, int $backupId, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, 'backup', $backupId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
