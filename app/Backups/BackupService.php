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
                'started_at' => $startedAt,
                'completed_at' => date('Y-m-d H:i:s'),
                'created_by_user_id' => $userId,
            ]);
            $this->audit($userId, 'backup_create', $backupId, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }
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
