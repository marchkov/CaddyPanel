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

if (strlen($password) < 12) {
    fwrite(STDERR, "Admin password must be at least 12 characters in production.\n");
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
    'default_php_version' => '8.4',
    'default_php_fpm_socket' => '/run/php/php8.4-fpm.sock',
    'backup_retention_days' => '14',
    'updates_auto_check' => '1',
    'updates_branch' => 'main',
];

foreach ($settings as $key => $value) {
    if (!$database->fetch('SELECT id FROM settings WHERE key = ?', [$key])) {
        $database->execute(
            'INSERT INTO settings (key, value, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$key, $value, $now, $now]
        );
    }
}

if (!$database->fetch('SELECT id FROM php_versions WHERE version = ?', ['8.4'])) {
    $database->execute(
        'INSERT INTO php_versions (version, fpm_socket, is_default, detected_at) VALUES (?, ?, 1, ?)',
        ['8.4', '/run/php/php8.4-fpm.sock', $now]
    );
}

echo "Production database initialized.\n";
