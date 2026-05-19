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
use CaddyPanel\Sites\SiteRepository;
use CaddyPanel\System\CommandRunner;

$config = require dirname(__DIR__) . '/config/app.php';
$database = new Database($config['database']['path']);

$jobRepository = new BackupJobRepository($database);
$jobService = new BackupJobService($jobRepository, $database);
$backupRepository = new BackupRepository($database);
$backupService = new BackupService(
    $backupRepository,
    new SiteRepository($database),
    new DatabaseRepository($database),
    new BackupProvisioner(
        new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
        dirname(__DIR__) . '/var/backups',
        $config['env'] ?? 'local'
    ),
    $database
);

$scheduler = new BackupScheduler($jobRepository, $jobService, $backupService, $backupRepository);
echo json_encode($scheduler->run(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
