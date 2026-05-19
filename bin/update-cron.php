<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CaddyPanel\Core\Database;
use CaddyPanel\Settings\SettingRepository;
use CaddyPanel\System\CommandRunner;
use CaddyPanel\Updates\UpdateService;

$config = require dirname(__DIR__) . '/config/app.php';
$database = new Database($config['database']['path']);
$settings = new SettingRepository($database);

if ($settings->get('updates_auto_check', '1') !== '1') {
    echo "Automatic update checks are disabled.\n";
    exit(0);
}

$updates = new UpdateService(
    new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
    $settings,
    $database,
    dirname(__DIR__)
);

$result = $updates->check(null, 'cli');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
