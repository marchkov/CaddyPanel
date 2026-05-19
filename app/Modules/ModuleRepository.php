<?php

namespace CaddyPanel\Modules;

use CaddyPanel\Core\Database;

class ModuleRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT * FROM modules ORDER BY name ASC');
    }

    public function find(string $name): ?array
    {
        return $this->database->fetch('SELECT * FROM modules WHERE name = ?', [$name]);
    }

    public function isEnabled(string $name): bool
    {
        $module = $this->find($name);
        return $module !== null && (int) $module['enabled'] === 1;
    }

    public function setEnabled(string $name, bool $enabled): void
    {
        $this->database->execute(
            'UPDATE modules SET enabled = ?, updated_at = ? WHERE name = ?',
            [$enabled ? 1 : 0, date('Y-m-d H:i:s'), $name]
        );
    }
}
