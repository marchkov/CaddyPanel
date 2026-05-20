<?php

namespace CaddyPanel\Databases;

use CaddyPanel\Core\Database;

class DatabaseRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll(
            'SELECT d.*, s.domain AS site_domain
             FROM databases d
             LEFT JOIN sites s ON s.id = d.site_id
             WHERE d.deleted_at IS NULL
             ORDER BY d.created_at DESC'
        );
    }

    public function find(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT d.*, s.domain AS site_domain
             FROM databases d
             LEFT JOIN sites s ON s.id = d.site_id
             WHERE d.id = ?',
            [$id]
        );
    }

    public function existsByName(string $name): bool
    {
        return $this->database->fetch(
            'SELECT id FROM databases WHERE name = ? AND deleted_at IS NULL',
            [$name]
        ) !== null;
    }

    public function findAnyByName(string $name): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM databases WHERE name = ?',
            [$name]
        );
    }

    public function findActiveBySiteId(int $siteId): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM databases
             WHERE site_id = ?
             AND deleted_at IS NULL
             AND status = ?
             ORDER BY created_at DESC
             LIMIT 1',
            [$siteId, 'active']
        );
    }

    public function forSite(int $siteId): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM databases
             WHERE site_id = ?
             AND deleted_at IS NULL
             ORDER BY created_at DESC',
            [$siteId]
        );
    }

    public function create(array $data): int
    {
        $this->database->execute(
            'INSERT INTO databases (site_id, name, username, password_encrypted, host, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['site_id'],
                $data['name'],
                $data['username'],
                $data['password_encrypted'],
                $data['host'],
                $data['status'],
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return $this->database->lastInsertId();
    }

    public function restoreDeleted(int $id, array $data): int
    {
        $this->database->execute(
            'UPDATE databases SET
                site_id = ?,
                username = ?,
                password_encrypted = ?,
                host = ?,
                status = ?,
                updated_at = ?,
                deleted_at = NULL
             WHERE id = ?',
            [
                $data['site_id'],
                $data['username'],
                $data['password_encrypted'],
                $data['host'],
                $data['status'],
                $data['updated_at'],
                $id,
            ]
        );

        return $id;
    }

    public function markDeleted(int $id, string $status = 'deleted'): void
    {
        $now = date('Y-m-d H:i:s');

        $this->database->execute(
            'UPDATE databases SET status = ?, updated_at = ?, deleted_at = ? WHERE id = ?',
            [$status, $now, $now, $id]
        );
    }

    public function updateSite(int $id, ?int $siteId): void
    {
        $this->database->execute(
            'UPDATE databases SET site_id = ?, updated_at = ? WHERE id = ?',
            [$siteId, date('Y-m-d H:i:s'), $id]
        );
    }
}
