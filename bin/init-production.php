<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CaddyPanel\Core\Database;
use CaddyPanel\Security\SecretKey;

$config = require dirname(__DIR__) . '/config/app.php';

$username = getenv('CADDYPANEL_ADMIN_USER') ?: null;
$password = getenv('CADDYPANEL_ADMIN_PASSWORD') ?: null;

if (!$username || !$password) {
    fwrite(STDERR, "CADDYPANEL_ADMIN_USER and CADDYPANEL_ADMIN_PASSWORD are required.\n");
    exit(2);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Admin password must be at least 8 characters in production.\n");
    exit(2);
}

$database = new Database($config['database']['path']);
$database->importSchema(dirname(__DIR__) . '/database/schema.sql');
SecretKey::load($config['paths']['secret_key'], true);

$now = date('Y-m-d H:i:s');
$existing = $database->fetch('SELECT id FROM users WHERE username = ?', [$username]);

if (!$existing) {
    $database->execute(
        'INSERT INTO users (username, password_hash, role, is_active, created_at, updated_at)
         VALUES (?, ?, ?, 1, ?, ?)',
        [$username, password_hash($password, PASSWORD_DEFAULT), 'admin', $now, $now]
    );
}

$modules = [
    'sites',
    'databases',
    'adminer',
    'filegator',
    'backups',
    'restore',
    'logs',
    'settings',
    'php_versions',
    'updates',
    'users',
];

foreach ($modules as $module) {
    if (!$database->fetch('SELECT id FROM modules WHERE name = ?', [$module])) {
        $database->execute(
            'INSERT INTO modules (name, enabled, config_json, created_at, updated_at)
             VALUES (?, 1, NULL, ?, ?)',
            [$module, $now, $now]
        );
    }
}

$settings = [
    'panel_domain' => getenv('CADDYPANEL_PANEL_DOMAIN') ?: 'localhost',
    'admin_email' => getenv('CADDYPANEL_ADMIN_EMAIL') ?: 'admin@example.com',
    'ui_theme' => 'dark',
    'default_php_version' => getenv('CADDYPANEL_DEFAULT_PHP_VERSION') ?: '8.4',
    'default_php_fpm_socket' => getenv('CADDYPANEL_DEFAULT_PHP_FPM_SOCKET') ?: '/run/php/php8.4-fpm.sock',
    'backup_retention_days' => '14',
    'backup_retention_count' => '7',
    'session_lifetime' => '3600',
    'security_ip_allowlist' => '',
    'health_check_token' => '',
    'updates_auto_check' => '1',
    'updates_branch' => 'main',
    'updates_repository_url' => getenv('CADDYPANEL_UPDATES_REPOSITORY_URL') ?: 'https://github.com/marchkov/CaddyPanel.git',
];

foreach ($settings as $key => $value) {
    if (!$database->fetch('SELECT id FROM settings WHERE key = ?', [$key])) {
        $database->execute(
            'INSERT INTO settings (key, value, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$key, $value, $now, $now]
        );
    }
}

$defaultPhpVersion = $settings['default_php_version'];
$defaultPhpSocket = $settings['default_php_fpm_socket'];

if (!$database->fetch('SELECT id FROM php_versions WHERE version = ?', [$defaultPhpVersion])) {
    $database->execute(
        'INSERT INTO php_versions (version, fpm_socket, is_default, detected_at) VALUES (?, ?, 1, ?)',
        [$defaultPhpVersion, $defaultPhpSocket, $now]
    );
}

echo "Production database initialized.\n";
