<?php

namespace CaddyPanel\Support;

use CaddyPanel\Core\Database;

class DevBootstrap
{
    public static function run(Database $database, string $schemaPath): void
    {
        $database->importSchema($schemaPath);

        $now = date('Y-m-d H:i:s');
        $password = getenv('CADDYPANEL_DEV_PASSWORD') ?: 'password123';

        if (!$database->fetch('SELECT id FROM users WHERE username = ?', ['admin'])) {
            $database->execute(
                'INSERT INTO users (username, password_hash, role, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?)',
                ['admin', password_hash($password, PASSWORD_DEFAULT), 'admin', $now, $now]
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
            'panel_domain' => 'localhost',
            'admin_email' => 'admin@example.com',
            'ui_theme' => 'dark',
            'default_php_version' => '8.4',
            'default_php_fpm_socket' => '/run/php/php8.4-fpm.sock',
            'backup_retention_days' => '14',
            'backup_retention_count' => '7',
            'updates_auto_check' => '1',
            'updates_branch' => 'main',
            'updates_repository_url' => 'https://github.com/marchkov/CaddyPanel.git',
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
    }
}
