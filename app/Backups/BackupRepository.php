<?php

namespace CaddyPanel\Backups;

use CaddyPanel\Core\Database;

class BackupRepository
{
    public function __construct(private Database $database)
    {
        $this->ensureQueueColumns();
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
            'INSERT INTO backup_runs (
                site_id, status, backup_file, file_size, message,
                include_files, include_database, include_caddy_config,
                started_at, completed_at, created_by_user_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['site_id'],
                $data['status'],
                $data['backup_file'] ?? null,
                $data['file_size'] ?? null,
                $data['message'] ?? null,
                !empty($data['include_files']) ? 1 : 0,
                !empty($data['include_database']) ? 1 : 0,
                !empty($data['include_caddy_config']) ? 1 : 0,
                $data['started_at'],
                $data['completed_at'] ?? null,
                $data['created_by_user_id'] ?? null,
            ]
        );

        return $this->database->lastInsertId();
    }

    public function nextQueued(): ?array
    {
        return $this->database->fetch(
            'SELECT b.*, s.domain, s.root_path, s.caddy_config_path
             FROM backup_runs b
             INNER JOIN sites s ON s.id = b.site_id
             WHERE b.status = ?
             AND s.deleted_at IS NULL
             ORDER BY b.started_at ASC, b.id ASC
             LIMIT 1',
            ['queued']
        );
    }

    public function markRunning(int $id): void
    {
        $this->database->execute(
            'UPDATE backup_runs SET status = ?, message = ? WHERE id = ?',
            ['running', 'Backup is running.', $id]
        );
    }

    public function markCompleted(int $id, string $status, ?string $file, ?int $size, string $message): void
    {
        $this->database->execute(
            'UPDATE backup_runs
             SET status = ?, backup_file = ?, file_size = ?, message = ?, completed_at = ?
             WHERE id = ?',
            [$status, $file, $size, $message, date('Y-m-d H:i:s'), $id]
        );
    }

    public function retry(int $id): void
    {
        $this->database->execute(
            'UPDATE backup_runs
             SET status = ?, backup_file = NULL, file_size = NULL, message = ?, started_at = ?, completed_at = NULL
             WHERE id = ?',
            ['queued', 'Backup is queued.', date('Y-m-d H:i:s'), $id]
        );
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

    public function automaticSuccessesBeyondLimit(int $siteId, int $keep): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM backup_runs
             WHERE site_id = ?
             AND status = ?
             AND created_by_user_id IS NULL
             AND completed_at IS NOT NULL
             ORDER BY completed_at DESC, id DESC
             LIMIT -1 OFFSET ?',
            [$siteId, 'success', $keep]
        );
    }

    public function markPruned(int $id, string $message): void
    {
        $this->database->execute(
            'UPDATE backup_runs SET status = ?, message = ? WHERE id = ?',
            ['pruned', $message, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM backup_runs WHERE id = ?', [$id]);
    }

    private function ensureQueueColumns(): void
    {
        $columns = array_column($this->database->fetchAll('PRAGMA table_info(backup_runs)'), 'name');

        foreach ([
            'include_files' => 'INTEGER NOT NULL DEFAULT 1',
            'include_database' => 'INTEGER NOT NULL DEFAULT 1',
            'include_caddy_config' => 'INTEGER NOT NULL DEFAULT 1',
        ] as $column => $definition) {
            if (!in_array($column, $columns, true)) {
                $this->database->execute('ALTER TABLE backup_runs ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }
}
