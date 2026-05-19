<?php

namespace CaddyPanel\Backups;

use CaddyPanel\Core\Database;

class BackupJobService
{
    public function __construct(
        private BackupJobRepository $jobs,
        private Database $database
    ) {
    }

    public function all(): array
    {
        return $this->jobs->all();
    }

    public function create(array $input, int $userId, string $ipAddress): int
    {
        $siteId = (int) ($input['site_id'] ?? 0);
        $scheduleType = (string) ($input['schedule_type'] ?? 'daily');
        $scheduleTime = (string) ($input['schedule_time'] ?? '03:00');
        $retentionDays = max(1, min(365, (int) ($input['retention_days'] ?? 14)));

        if ($siteId <= 0) {
            throw new \InvalidArgumentException('Select a site.');
        }

        if (!in_array($scheduleType, ['hourly', 'daily', 'weekly'], true)) {
            throw new \InvalidArgumentException('Invalid schedule type.');
        }

        if (preg_match('/^\d{2}:\d{2}$/', $scheduleTime) !== 1) {
            throw new \InvalidArgumentException('Invalid schedule time.');
        }

        $id = $this->jobs->create([
            'site_id' => $siteId,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'schedule_type' => $scheduleType,
            'schedule_time' => $scheduleTime,
            'include_files' => !empty($input['include_files']) ? 1 : 0,
            'include_database' => !empty($input['include_database']) ? 1 : 0,
            'include_caddy_config' => !empty($input['include_caddy_config']) ? 1 : 0,
            'retention_days' => $retentionDays,
            'next_run_at' => $this->nextRun($scheduleType, $scheduleTime),
        ]);

        $this->audit($userId, 'backup_job_create', $id, 'success', 'Created backup job.', $ipAddress);

        return $id;
    }

    public function nextRun(string $type, string $time): string
    {
        $now = new \DateTimeImmutable();

        if ($type === 'hourly') {
            return $now->modify('+1 hour')->format('Y-m-d H:i:s');
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));
        $candidate = $now->setTime($hour, $minute);

        if ($candidate <= $now) {
            $candidate = $candidate->modify($type === 'weekly' ? '+1 week' : '+1 day');
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    private function audit(int $userId, string $action, int $jobId, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, 'backup_job', $jobId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
