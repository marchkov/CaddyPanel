<?php

namespace CaddyPanel\Restore;

use CaddyPanel\Backups\BackupRepository;
use CaddyPanel\Backups\BackupService;
use CaddyPanel\Core\Database;
use CaddyPanel\Databases\DatabaseRepository;

class RestoreService
{
    public function __construct(
        private BackupRepository $backups,
        private BackupService $backupService,
        private DatabaseRepository $databases,
        private RestoreProvisioner $provisioner,
        private Database $database
    ) {
    }

    public function availableBackups(): array
    {
        return $this->backups->all();
    }

    public function inspect(int $backupId): array
    {
        $backup = $this->backups->find($backupId);

        if (!$backup) {
            throw new \InvalidArgumentException('Backup not found.');
        }

        return [
            'backup' => $backup,
            'inspection' => $this->provisioner->inspect($backup['backup_file'] ?? null),
        ];
    }

    public function restore(int $backupId, array $options, int $userId, string $ipAddress): array
    {
        $backup = $this->backups->find($backupId);

        if (!$backup) {
            throw new \InvalidArgumentException('Backup not found.');
        }

        if (($backup['status'] ?? '') !== 'success') {
            throw new \InvalidArgumentException('Only successful backups can be restored.');
        }

        $selected = [
            'files' => !empty($options['restore_files']),
            'database' => !empty($options['restore_database']),
            'host_config' => !empty($options['restore_host_config']),
        ];

        if (!in_array(true, $selected, true)) {
            throw new \InvalidArgumentException('Choose at least one restore mode.');
        }

        $inspection = $this->provisioner->inspect($backup['backup_file'] ?? null);

        if (empty($inspection['exists'])) {
            throw new \InvalidArgumentException($inspection['message'] ?? 'Backup file is not available.');
        }

        if ($selected['database'] && isset($inspection['manifest']['contains']['database']) && !$inspection['manifest']['contains']['database']) {
            throw new \InvalidArgumentException('This backup does not contain a database dump.');
        }

        $targetDatabase = null;

        if ($selected['database']) {
            $targetDatabase = $this->databases->findActiveBySiteId((int) $backup['site_id']);

            if (!$targetDatabase) {
                throw new \InvalidArgumentException('Site does not have an active linked database to restore into.');
            }
        }

        try {
            $preRestoreBackupId = $this->backupService->createForSite((int) $backup['site_id'], $userId, $ipAddress);
            $restore = $this->provisioner->restore($backup, $selected, $targetDatabase);
            $message = 'Restore applied. Modes: ' . implode(', ', array_keys(array_filter($selected))) . '. Pre-restore backup: #' . $preRestoreBackupId . '. ' . $restore['message'];
            $this->audit($userId, 'restore_backup', $backupId, 'success', $message, $ipAddress);
        } catch (\Throwable $exception) {
            $this->audit($userId, 'restore_backup', $backupId, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }

        return [
            'backup' => $backup,
            'selected' => $selected,
            'pre_restore_backup_id' => $preRestoreBackupId,
            'restore' => $restore,
            'message' => $message,
        ];
    }

    private function audit(int $userId, string $action, int $backupId, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, 'backup', $backupId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
