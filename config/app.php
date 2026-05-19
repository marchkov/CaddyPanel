<?php

return [
    'name' => 'CaddyPanel',
    'version' => '0.1.0',
    'env' => getenv('APP_ENV') ?: 'local',
    'base_url' => getenv('APP_URL') ?: 'http://127.0.0.1:8080',

    'database' => [
        'path' => getenv('CADDYPANEL_DB_PATH') ?: dirname(__DIR__) . '/var/data/caddypanel.sqlite',
    ],

    'security' => [
        'session_name' => 'caddypanel_session',
        'session_lifetime' => 3600,
    ],

    'paths' => [
        'sites' => getenv('CADDYPANEL_SITES_PATH') ?: '/var/www/sites',
        'caddy_sites' => getenv('CADDYPANEL_CADDY_SITES_PATH') ?: '/etc/caddy/sites',
        'backups' => getenv('CADDYPANEL_BACKUPS_PATH') ?: '/var/www/sites/backup',
        'logs' => getenv('CADDYPANEL_LOGS_PATH') ?: dirname(__DIR__) . '/var/logs',
        'secret_key' => getenv('CADDYPANEL_SECRET_KEY_PATH') ?: dirname(__DIR__) . '/config/secret.key',
        'adminer' => getenv('CADDYPANEL_ADMINER_PATH') ?: dirname(__DIR__) . '/apps/adminer',
        'filegator' => getenv('CADDYPANEL_FILEGATOR_PATH') ?: dirname(__DIR__) . '/apps/filegator',
    ],
];
