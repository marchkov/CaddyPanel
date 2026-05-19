<?php

namespace CaddyPanel\Settings;

use CaddyPanel\Core\Database;

class SettingRepository
{
    public function __construct(private Database $database)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->database->fetch('SELECT value FROM settings WHERE key = ?', [$key]);

        return $setting['value'] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');

        if ($this->database->fetch('SELECT id FROM settings WHERE key = ?', [$key])) {
            $this->database->execute(
                'UPDATE settings SET value = ?, updated_at = ? WHERE key = ?',
                [$value, $now, $key]
            );
            return;
        }

        $this->database->execute(
            'INSERT INTO settings (key, value, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$key, $value, $now, $now]
        );
    }
}
