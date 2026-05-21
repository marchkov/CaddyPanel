<?php

namespace CaddyPanel\Modules;

class ModuleService
{
    public function __construct(private ModuleRepository $modules)
    {
    }

    public function enabledNavigation(): array
    {
        $items = [
            'sites' => ['label' => 'Sites', 'path' => '/sites', 'admin_only' => false],
            'databases' => ['label' => 'Databases', 'path' => '/databases', 'admin_only' => false],
            'adminer' => ['label' => 'Adminer', 'path' => '/db', 'admin_only' => false],
            'filegator' => ['label' => 'Files', 'path' => '/files', 'admin_only' => false],
            'backups' => ['label' => 'Backups', 'path' => '/backups', 'admin_only' => false],
            'logs' => ['label' => 'Logs', 'path' => '/logs', 'admin_only' => false],
            'admin_tasks' => ['label' => 'Admin Tasks', 'path' => '/admin-tasks', 'admin_only' => true],
            'firewall' => ['label' => 'Firewall', 'path' => '/firewall', 'admin_only' => true],
            'php_versions' => ['label' => 'PHP Versions', 'path' => '/php-versions', 'admin_only' => true],
            'settings' => ['label' => 'Settings', 'path' => '/settings', 'admin_only' => true],
            'updates' => ['label' => 'Updates', 'path' => '/updates', 'admin_only' => true],
            'users' => ['label' => 'Users', 'path' => '/users', 'admin_only' => true],
        ];

        return array_filter(
            $items,
            fn (array $item, string $name): bool => $this->modules->isEnabled($name),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function isEnabled(string $name): bool
    {
        return $this->modules->isEnabled($name);
    }

    public function all(): array
    {
        return $this->modules->all();
    }

    public function setEnabled(string $name, bool $enabled): void
    {
        $this->modules->setEnabled($name, $enabled);
    }
}
