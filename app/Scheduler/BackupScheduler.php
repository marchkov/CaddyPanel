<?php

namespace CaddyPanel\Scheduler;

use CaddyPanel\Backups\BackupJobRepository;
use CaddyPanel\Backups\BackupJobService;
use CaddyPanel\Backups\BackupRepository;
use CaddyPanel\Backups\BackupService;

class BackupScheduler
{
    public function __construct(
        private BackupJobRepository $jobs,
        private BackupJobService $jobService,
        private BackupService $backupService,
        private BackupRepository $backupRuns
    ) {
    }

    public function run(): array
    {
        $now = date('Y-m-d H:i:s');
        $dueJobs = $this->jobs->due($now);
        $results = [];

        foreach ($dueJobs as $job) {
            try {
                $backupId = $this->backupService->createForSite((int) $job['site_id'], null, 'cli', [
                    'include_files' => (int) $job['include_files'] === 1,
                    'include_database' => (int) $job['include_database'] === 1,
                    'include_caddy_config' => (int) $job['include_caddy_config'] === 1,
                ]);
                $pruned = $this->pruneOldBackups((int) $job['site_id'], (int) $job['retention_days']);
                $nextRun = $this->jobService->nextRun($job['schedule_type'], $job['schedule_time']);
                $this->jobs->updateRunState((int) $job['id'], date('Y-m-d H:i:s'), $nextRun);
                $results[] = [
                    'job_id' => (int) $job['id'],
                    'site_id' => (int) $job['site_id'],
                    'backup_id' => $backupId,
                    'status' => 'success',
                    'pruned' => $pruned,
                    'next_run_at' => $nextRun,
                ];
            } catch (\Throwable $exception) {
                $nextRun = $this->jobService->nextRun($job['schedule_type'], $job['schedule_time']);
                $this->jobs->updateRunState((int) $job['id'], date('Y-m-d H:i:s'), $nextRun);
                $results[] = [
                    'job_id' => (int) $job['id'],
                    'site_id' => (int) $job['site_id'],
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'next_run_at' => $nextRun,
                ];
            }
        }

        return $results;
    }

    private function pruneOldBackups(int $siteId, int $retentionDays): int
    {
        $cutoff = (new \DateTimeImmutable())->modify('-' . max(1, $retentionDays) . ' days')->format('Y-m-d H:i:s');
        $oldRuns = $this->backupRuns->olderThanForSite($siteId, $cutoff);
        $count = 0;

        foreach ($oldRuns as $run) {
            if (!empty($run['backup_file']) && is_file($run['backup_file'])) {
                @unlink($run['backup_file']);
            }

            $this->backupRuns->markPruned((int) $run['id'], 'Pruned by scheduler retention policy.');
            $count++;
        }

        return $count;
    }
}
