<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CaddyPanel\Backups\BackupJobRepository;
use CaddyPanel\Backups\BackupJobService;
use CaddyPanel\Backups\BackupProvisioner;
use CaddyPanel\Backups\BackupRepository;
use CaddyPanel\Backups\BackupService;
use CaddyPanel\Core\Database;
use CaddyPanel\Databases\DatabaseRepository;
use CaddyPanel\Scheduler\BackupScheduler;
use CaddyPanel\Settings\SettingRepository;
use CaddyPanel\Sites\SiteRepository;
use CaddyPanel\System\CommandRunner;

$config = require dirname(__DIR__) . '/config/app.php';
$database = new Database($config['database']['path']);

$lockPath = dirname(__DIR__) . '/var/backup-worker.lock';
$lockHandle = fopen($lockPath, 'c');

if ($lockHandle === false) {
    fwrite(STDERR, "Unable to open backup worker lock.\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo json_encode(['status' => 'already_running'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$jobRepository = new BackupJobRepository($database);
$jobService = new BackupJobService($jobRepository, $database);
$backupRepository = new BackupRepository($database);
$settings = new SettingRepository($database);
$automaticBackupsToKeep = max(1, min(365, (int) $settings->get('backup_retention_count', '7')));
$backupService = new BackupService(
    $backupRepository,
    new SiteRepository($database),
    new DatabaseRepository($database),
    new BackupProvisioner(
        new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
        $config['paths']['backups'],
        $config['env'] ?? 'local'
    ),
    $database
);

$scheduler = new BackupScheduler($jobRepository, $jobService, $backupService, $backupRepository, $automaticBackupsToKeep);
echo json_encode([
    'queued' => $backupService->processQueued(),
    'scheduled' => $scheduler->run(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
