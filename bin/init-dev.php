<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CaddyPanel\Core\Database;
use CaddyPanel\Support\DevBootstrap;

$config = require dirname(__DIR__) . '/config/app.php';
$database = new Database($config['database']['path']);
DevBootstrap::run($database, dirname(__DIR__) . '/database/schema.sql');

echo "SQLite database: " . $config['database']['path'] . "\n";
echo "Development login: admin / " . (getenv('CADDYPANEL_DEV_PASSWORD') ?: 'password123') . "\n";

if (!extension_loaded('openssl')) {
    echo "Warning: PHP OpenSSL extension is not enabled. Database password encryption will not work.\n";
}
