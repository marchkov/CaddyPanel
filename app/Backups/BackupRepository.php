<?php

namespace CaddyPanel\Backups;

use CaddyPanel\Core\Database;

class BackupRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll(
            'SELECT b.*, s.domain, s.root_path, s.caddy_config_path
             FROM backup_runs b
             INNER JOIN sites s ON s.id = b.site_id
             ORDER BY b.started_at DESC'
        );
    }

    public function find(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT b.*, s.domain, s.root_path, s.caddy_config_path
             FROM backup_runs b
             INNER JOIN sites s ON s.id = b.site_id
             WHERE b.id = ?',
            [$id]
        );
    }

    public function create(array $data): int
    {
        $this->database->execute(
            'INSERT INTO backup_runs (site_id, status, backup_file, file_size, message, started_at, completed_at, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['site_id'],
                $data['status'],
                $data['backup_file'],
                $data['file_size'],
                $data['message'],
                $data['started_at'],
                $data['completed_at'],
                $data['created_by_user_id'],
            ]
        );

        return $this->database->lastInsertId();
    }

    public function olderThanForSite(int $siteId, string $cutoff): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM backup_runs
             WHERE site_id = ?
             AND status = ?
             AND completed_at IS NOT NULL
             AND completed_at < ?
             ORDER BY completed_at ASC',
            [$siteId, 'success', $cutoff]
        );
    }

    public function markPruned(int $id, string $message): void
    {
        $this->database->execute(
            'UPDATE backup_runs SET status = ?, message = ? WHERE id = ?',
            ['pruned', $message, $id]
        );
    }
}
