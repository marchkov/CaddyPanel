<?php

namespace CaddyPanel\Users;

use CaddyPanel\Core\Database;

class UserRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT id, username, role, is_active, created_at, updated_at FROM users ORDER BY username ASC');
    }

    public function find(int $id): ?array
    {
        return $this->database->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->database->fetch('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public function create(string $username, string $passwordHash, string $role): int
    {
        $now = date('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO users (username, password_hash, role, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, ?)',
            [$username, $passwordHash, $role, $now, $now]
        );

        return $this->database->lastInsertId();
    }

    public function activeAdminCount(): int
    {
        $row = $this->database->fetch(
            'SELECT COUNT(*) AS count FROM users WHERE role = ? AND is_active = 1',
            ['admin']
        );

        return (int) ($row['count'] ?? 0);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->database->execute(
            'UPDATE users SET is_active = ?, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, date('Y-m-d H:i:s'), $id]
        );
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->database->execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [$passwordHash, date('Y-m-d H:i:s'), $id]
        );
    }
}
