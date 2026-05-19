<?php

namespace CaddyPanel\Backups;

use CaddyPanel\Core\Database;

class BackupJobRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll(
            'SELECT j.*, s.domain
             FROM backup_jobs j
             INNER JOIN sites s ON s.id = j.site_id
             WHERE s.deleted_at IS NULL
             ORDER BY s.domain ASC'
        );
    }

    public function due(string $now): array
    {
        return $this->database->fetchAll(
            'SELECT j.*, s.domain, s.root_path, s.caddy_config_path
             FROM backup_jobs j
             INNER JOIN sites s ON s.id = j.site_id
             WHERE j.enabled = 1
             AND s.deleted_at IS NULL
             AND (j.next_run_at IS NULL OR j.next_run_at <= ?)
             ORDER BY j.next_run_at ASC',
            [$now]
        );
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO backup_jobs (
                site_id, enabled, schedule_type, schedule_time, include_files,
                include_database, include_caddy_config, retention_days,
                next_run_at, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['site_id'],
                $data['enabled'],
                $data['schedule_type'],
                $data['schedule_time'],
                $data['include_files'],
                $data['include_database'],
                $data['include_caddy_config'],
                $data['retention_days'],
                $data['next_run_at'],
                $now,
                $now,
            ]
        );

        return $this->database->lastInsertId();
    }

    public function updateRunState(int $id, string $lastRunAt, string $nextRunAt): void
    {
        $this->database->execute(
            'UPDATE backup_jobs SET last_run_at = ?, next_run_at = ?, updated_at = ? WHERE id = ?',
            [$lastRunAt, $nextRunAt, date('Y-m-d H:i:s'), $id]
        );
    }
}
