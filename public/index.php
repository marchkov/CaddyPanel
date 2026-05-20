<?php

use CaddyPanel\Auth\AuthController;
use CaddyPanel\Auth\AuthService;
use CaddyPanel\Apps\AppController;
use CaddyPanel\Apps\AppLocator;
use CaddyPanel\Backups\BackupController;
use CaddyPanel\Backups\BackupProvisioner;
use CaddyPanel\Backups\BackupRepository;
use CaddyPanel\Backups\BackupService;
use CaddyPanel\Caddy\CaddyConfigApplier;
use CaddyPanel\Caddy\CaddyConfigRenderer;
use CaddyPanel\Core\Database;
use CaddyPanel\Databases\DatabaseController;
use CaddyPanel\Databases\DatabaseProvisioner;
use CaddyPanel\Databases\DatabaseRepository;
use CaddyPanel\Databases\DatabaseService;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Core\Router;
use CaddyPanel\Logs\AuditLogRepository;
use CaddyPanel\Logs\LogController;
use CaddyPanel\Modules\ModuleRepository;
use CaddyPanel\Modules\ModuleService;
use CaddyPanel\PhpVersions\PhpVersionController;
use CaddyPanel\PhpVersions\PhpVersionRepository;
use CaddyPanel\PhpVersions\PhpVersionService;
use CaddyPanel\Restore\RestoreController;
use CaddyPanel\Restore\RestoreProvisioner;
use CaddyPanel\Restore\RestoreService;
use CaddyPanel\Settings\SettingRepository;
use CaddyPanel\Settings\SettingsController;
use CaddyPanel\Sites\SiteController;
use CaddyPanel\Sites\SiteProvisioner;
use CaddyPanel\Sites\SiteRepository;
use CaddyPanel\Sites\SiteService;
use CaddyPanel\Security\Encryptor;
use CaddyPanel\Security\SecretKey;
use CaddyPanel\Support\AuthGuard;
use CaddyPanel\Support\DevBootstrap;
use CaddyPanel\System\CommandRunner;
use CaddyPanel\SystemStatus\SystemStatusService;
use CaddyPanel\Updates\UpdateController;
use CaddyPanel\Updates\UpdateService;
use CaddyPanel\Users\UserController;
use CaddyPanel\Users\UserRepository;
use CaddyPanel\Users\UserService;

$config = require dirname(__DIR__) . '/config/app.php';

session_name($config['security']['session_name']);
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require dirname(__DIR__) . '/vendor/autoload.php';

$request = new Request();
$database = new Database($config['database']['path']);

if (($config['env'] ?? 'local') === 'local') {
    DevBootstrap::run($database, dirname(__DIR__) . '/database/schema.sql');
}

$auth = new AuthService($database);
$modules = new ModuleService(new ModuleRepository($database));
$settings = new SettingRepository($database);
$guard = new AuthGuard($auth, $modules);
$router = new Router($request);
$authController = new AuthController($auth);
$secretKey = SecretKey::load($config['paths']['secret_key'], ($config['env'] ?? 'local') === 'local');
$siteProvisioner = new SiteProvisioner(
    new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local')
);
$caddyRenderer = new CaddyConfigRenderer(
    dirname(__DIR__) . '/caddy/templates',
    dirname(__DIR__) . '/var/generated/caddy',
    [
        'php_fpm_socket' => $settings->get('default_php_fpm_socket', '/run/php/php8.4-fpm.sock'),
        'roll_size' => '10MB',
        'roll_keep' => '10',
        'roll_keep_for' => '720h',
    ]
);
$caddyApplier = new CaddyConfigApplier(
    new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local')
);
$phpVersionRepository = new PhpVersionRepository($database);
$phpVersions = new PhpVersionService(
    $phpVersionRepository,
    $settings,
    new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
    $database,
    $config['env'] ?? 'local'
);
$databaseService = new DatabaseService(
    new DatabaseRepository($database),
    $database,
    new DatabaseProvisioner(new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local')),
    new Encryptor($secretKey)
);
$siteController = new SiteController(
    $siteService = new SiteService(
        new SiteRepository($database),
        $database,
        $siteProvisioner,
        $caddyRenderer,
        $caddyApplier,
        $phpVersionRepository,
        $databaseService,
        new DatabaseRepository($database)
    ),
    $phpVersions,
    $guard,
    $viewData = function (array $data = []) use ($auth, $modules, $settings): array {
        return array_merge([
            'user' => $auth->user(),
            'navigation' => $modules->enabledNavigation(),
            'theme' => $settings->get('ui_theme', 'dark'),
        ], $data);
    }
);
$backupService = new BackupService(
    new BackupRepository($database),
    new SiteRepository($database),
    new DatabaseRepository($database),
    new BackupProvisioner(
        new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
        $config['paths']['backups'],
        $config['env'] ?? 'local'
    ),
    $database
);
$backupController = new BackupController(
    $backupService,
    new \CaddyPanel\Backups\BackupJobService(new \CaddyPanel\Backups\BackupJobRepository($database), $database),
    $siteService,
    $guard,
    $viewData
);
$restoreController = new RestoreController(
    new RestoreService(
        new BackupRepository($database),
        $backupService,
        new DatabaseRepository($database),
        new RestoreProvisioner(
            new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
            $config['paths']['backups'],
            $config['env'] ?? 'local'
        ),
        $database
    ),
    $guard,
    $viewData
);
$databaseController = new DatabaseController(
    $databaseService,
    $guard,
    $viewData
);
$settingsController = new SettingsController($settings, $modules, $database, $guard, $viewData);
$phpVersionController = new PhpVersionController($phpVersions, $guard, $viewData);
$appController = new AppController(
    new AppLocator($config['paths']['adminer'], $config['paths']['filegator']),
    $guard,
    $viewData
);
$logController = new LogController(
    new AuditLogRepository($database),
    new SiteRepository($database),
    new \CaddyPanel\Logs\SiteLogReader(),
    $guard,
    $viewData
);
$updateController = new UpdateController(
    new UpdateService(
        new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
        $settings,
        $database,
        dirname(__DIR__)
    ),
    $guard,
    $viewData
);
$userController = new UserController(
    new UserService(new UserRepository($database), $database),
    $guard,
    $viewData
);
$systemStatus = new SystemStatusService(
    new CommandRunner(dirname(__DIR__) . '/bin', $config['env'] ?? 'local'),
    $config['env'] ?? 'local'
);

$router->get('/', fn () => Response::redirect('/dashboard'));
$router->get('/login', [$authController, 'login']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/auth/check', [$authController, 'check']);

$router->get('/dashboard', function () use ($guard, $database, $viewData, $systemStatus): void {
    $guard->requireLogin();

    Response::view('dashboard/index', $viewData([
        'stats' => [
            'sites' => (int) ($database->fetch('SELECT COUNT(*) AS count FROM sites WHERE deleted_at IS NULL')['count'] ?? 0),
            'databases' => (int) ($database->fetch('SELECT COUNT(*) AS count FROM databases WHERE deleted_at IS NULL')['count'] ?? 0),
            'backups' => (int) ($database->fetch('SELECT COUNT(*) AS count FROM backup_runs')['count'] ?? 0),
        ],
        'systemStatus' => $systemStatus->current(),
    ]));
});

$placeholderModules = ['sites', 'databases', 'adminer', 'filegator', 'backups', 'logs'];

$router->get('/sites', [$siteController, 'index']);
$router->get('/sites/create', [$siteController, 'create']);
$router->post('/sites/create', [$siteController, 'create']);
$router->get('/sites/{id}', [$siteController, 'show']);
$router->get('/sites/{id}/edit', [$siteController, 'edit']);
$router->post('/sites/{id}/edit', [$siteController, 'edit']);
$router->post('/sites/{id}/caddy-config', [$siteController, 'caddyConfig']);
$router->get('/sites/{id}/delete', [$siteController, 'delete']);
$router->post('/sites/{id}/delete', [$siteController, 'delete']);
$router->get('/databases', [$databaseController, 'index']);
$router->get('/databases/create', [$databaseController, 'create']);
$router->post('/databases/create', [$databaseController, 'create']);
$router->get('/databases/{id}', [$databaseController, 'show']);
$router->post('/databases/{id}', [$databaseController, 'show']);
$router->get('/databases/{id}/delete', [$databaseController, 'delete']);
$router->post('/databases/{id}/delete', [$databaseController, 'delete']);
$router->get('/db', [$appController, 'adminer']);
$router->post('/db', [$appController, 'adminer']);
$router->get('/files', [$appController, 'filegator']);
$router->get('/logs', [$logController, 'index']);
$router->get('/logs/sites/{id}', [$logController, 'site']);
$router->get('/backups', [$backupController, 'index']);
$router->post('/backups/create', [$backupController, 'create']);
$router->get('/backups/{id}/download', [$backupController, 'download']);
$router->post('/backups/{id}/delete', [$backupController, 'delete']);
$router->post('/backups/{id}/retry', [$backupController, 'retry']);
$router->post('/backups/jobs/create', [$backupController, 'createJob']);
$router->get('/restore', [$restoreController, 'index']);
$router->get('/restore/{id}', [$restoreController, 'show']);
$router->post('/restore/{id}', [$restoreController, 'apply']);
$router->get('/updates', [$updateController, 'index']);
$router->post('/updates', [$updateController, 'action']);
$router->get('/php-versions', [$phpVersionController, 'index']);
$router->post('/php-versions', [$phpVersionController, 'index']);
$router->get('/users', [$userController, 'index']);
$router->get('/users/create', [$userController, 'create']);
$router->post('/users/create', [$userController, 'create']);
$router->post('/users/{id}/action', [$userController, 'action']);

foreach ($placeholderModules as $moduleName) {
    if ($moduleName === 'sites') {
        continue;
    }

    if ($moduleName === 'databases') {
        continue;
    }

    if (in_array($moduleName, ['adminer', 'filegator'], true)) {
        continue;
    }

    if ($moduleName === 'logs') {
        continue;
    }

    if ($moduleName === 'backups') {
        continue;
    }

    if ($moduleName === 'updates') {
        continue;
    }

    if ($moduleName === 'users') {
        continue;
    }

    $path = match ($moduleName) {
        'adminer' => '/db',
        'filegator' => '/files',
        default => '/' . $moduleName,
    };

    $router->get($path, function () use ($guard, $viewData, $moduleName): void {
        $guard->requireModule($moduleName, $viewData());

        Response::view('errors/not-implemented', $viewData([
            'section' => ucfirst($moduleName),
        ]));
    });
}

$router->get('/settings', [$settingsController, 'index']);
$router->post('/settings', [$settingsController, 'index']);

$router->dispatch();
