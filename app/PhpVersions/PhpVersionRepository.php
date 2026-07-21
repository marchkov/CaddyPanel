<?php

namespace CaddyPanel\PhpVersions;

use CaddyPanel\Core\Database;

class PhpVersionRepository
{
    public function __construct(private Database $database)
    {
        $this->ensureJobSchema();
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT * FROM php_versions ORDER BY version DESC');
    }

    public function findByVersion(string $version): ?array
    {
        return $this->database->fetch('SELECT * FROM php_versions WHERE version = ?', [$version]);
    }

    public function replaceDetected(array $versions): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->transaction(function () use ($versions, $now): void {
            $this->database->execute('DELETE FROM php_versions');

            foreach ($versions as $version) {
                $this->database->execute(
                    'INSERT INTO php_versions (version, fpm_socket, is_default, detected_at) VALUES (?, ?, ?, ?)',
                    [$version['version'], $version['fpm_socket'], !empty($version['is_default']) ? 1 : 0, $now]
                );
            }
        });
    }

    public function setDefault(string $version): void
    {
        $this->database->transaction(function () use ($version): void {
            $this->database->execute('UPDATE php_versions SET is_default = 0');
            $this->database->execute('UPDATE php_versions SET is_default = 1 WHERE version = ?', [$version]);
        });
    }

    public function createJob(string $action, string $version, int $userId, string $ipAddress): int
    {
        $now = date('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO php_version_jobs (action, version, status, created_by_user_id, ip_address, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$action, $version, 'queued', $userId, $ipAddress, $now, $now]
        );

        return $this->database->lastInsertId();
    }

    public function findActiveJobForVersion(string $version): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM php_version_jobs
             WHERE version = ? AND status IN (?, ?)
             ORDER BY id DESC
             LIMIT 1',
            [$version, 'queued', 'running']
        );
    }

    public function recentJobs(int $limit = 20): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM php_version_jobs ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
        );
    }

    public function latestJobsByVersion(int $limit = 100): array
    {
        $rows = $this->recentJobs($limit);
        $jobs = [];

        foreach ($rows as $row) {
            $version = (string) $row['version'];

            if (!isset($jobs[$version])) {
                $jobs[$version] = $row;
            }
        }

        return $jobs;
    }

    public function failJobStart(int $jobId, string $output): void
    {
        $this->database->execute(
            'UPDATE php_version_jobs
             SET status = ?, exit_code = ?, output = ?, finished_at = ?, updated_at = ?
             WHERE id = ?',
            ['failed', 1, $output, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $jobId]
        );
    }

    private function ensureJobSchema(): void
    {
        $this->database->execute(
            'CREATE TABLE IF NOT EXISTS php_version_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT NOT NULL,
                version TEXT NOT NULL,
                status TEXT NOT NULL,
                exit_code INTEGER,
                output TEXT,
                created_by_user_id INTEGER,
                ip_address TEXT,
                started_at TEXT,
                finished_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
            )'
        );
        $this->database->execute('CREATE INDEX IF NOT EXISTS idx_php_version_jobs_status ON php_version_jobs(status)');
        $this->database->execute('CREATE INDEX IF NOT EXISTS idx_php_version_jobs_version ON php_version_jobs(version)');
    }
}
