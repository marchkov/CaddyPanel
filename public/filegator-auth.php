<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CaddyPanel\Core\Database;
use CaddyPanel\Core\ErrorHandler;
use CaddyPanel\Core\SecurityHeaders;
use CaddyPanel\Modules\ModuleRepository;
use CaddyPanel\Modules\ModuleService;

$config = require dirname(__DIR__) . '/config/app.php';
$env = $config['env'] ?? 'local';

ErrorHandler::register($config['paths']['logs'], $env === 'production');
SecurityHeaders::send();

session_name($config['security']['session_name']);
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $env === 'production',
    'cookie_samesite' => 'Lax',
]);

$user = $_SESSION['user'] ?? null;

if (!$user || !in_array($user['role'] ?? null, ['admin', 'manager'], true)) {
    header('Location: /login', true, 302);
    exit;
}

$database = new Database($config['database']['path']);
$modules = new ModuleService(new ModuleRepository($database));

if (!$modules->isEnabled('filegator')) {
    http_response_code(403);
    echo 'Module disabled';
    exit;
}

http_response_code(204);
