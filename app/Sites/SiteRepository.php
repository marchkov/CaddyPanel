<?php

namespace CaddyPanel\Sites;

use CaddyPanel\Core\Database;

class SiteRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM sites WHERE deleted_at IS NULL ORDER BY created_at DESC'
        );
    }

    public function find(int $id): ?array
    {
        return $this->database->fetch('SELECT * FROM sites WHERE id = ?', [$id]);
    }

    public function findByDomain(string $domain): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM sites WHERE domain = ? AND deleted_at IS NULL',
            [$domain]
        );
    }

    public function findAnyByDomain(string $domain): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM sites WHERE domain = ?',
            [$domain]
        );
    }

    public function aliasExists(string $domain, ?int $exceptSiteId = null): bool
    {
        $site = $this->database->fetch(
            'SELECT id FROM sites WHERE domain = ? AND deleted_at IS NULL',
            [$domain]
        );

        if ($site && (int) $site['id'] !== $exceptSiteId) {
            return true;
        }

        $params = [$domain];
        $sql = 'SELECT a.site_id
                FROM site_aliases a
                INNER JOIN sites s ON s.id = a.site_id
                WHERE a.domain = ? AND s.deleted_at IS NULL';

        if ($exceptSiteId !== null) {
            $sql .= ' AND a.site_id != ?';
            $params[] = $exceptSiteId;
        }

        return $this->database->fetch($sql, $params) !== null;
    }

    public function create(array $data, array $aliases): int
    {
        $this->database->execute(
            'INSERT INTO sites (
                domain, type, root_path, public_path, private_path, logs_path, tmp_path,
                php_enabled, php_version, php_fpm_socket, caddy_config_path, status,
                created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['domain'],
                $data['type'],
                $data['root_path'],
                $data['public_path'],
                $data['private_path'],
                $data['logs_path'],
                $data['tmp_path'],
                $data['php_enabled'],
                $data['php_version'],
                $data['php_fpm_socket'],
                $data['caddy_config_path'],
                $data['status'],
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        $siteId = $this->database->lastInsertId();
        $this->replaceAliases($siteId, $aliases);

        return $siteId;
    }

    public function restoreDeleted(int $siteId, array $data, array $aliases): int
    {
        $this->database->execute(
            'UPDATE sites SET
                type = ?,
                root_path = ?,
                public_path = ?,
                private_path = ?,
                logs_path = ?,
                tmp_path = ?,
                php_enabled = ?,
                php_version = ?,
                php_fpm_socket = ?,
                caddy_config_path = ?,
                status = ?,
                last_error = NULL,
                updated_at = ?,
                deleted_at = NULL
             WHERE id = ?',
            [
                $data['type'],
                $data['root_path'],
                $data['public_path'],
                $data['private_path'],
                $data['logs_path'],
                $data['tmp_path'],
                $data['php_enabled'],
                $data['php_version'],
                $data['php_fpm_socket'],
                $data['caddy_config_path'],
                $data['status'],
                $data['updated_at'],
                $siteId,
            ]
        );

        $this->replaceAliases($siteId, $aliases);

        return $siteId;
    }

    public function aliases(int $siteId): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM site_aliases WHERE site_id = ? ORDER BY domain ASC',
            [$siteId]
        );
    }

    public function updateStatus(int $id, string $status, ?string $message = null): void
    {
        $this->database->execute(
            "UPDATE sites SET status = ?, last_error = ?, updated_at = ?, deleted_at = CASE WHEN ? = 'deleted' THEN ? ELSE deleted_at END WHERE id = ?",
            [$status, $message, date('Y-m-d H:i:s'), $status, date('Y-m-d H:i:s'), $id]
        );
    }

    private function replaceAliases(int $siteId, array $aliases): void
    {
        $this->database->execute('DELETE FROM site_aliases WHERE site_id = ?', [$siteId]);

        foreach ($aliases as $alias) {
            $this->database->execute(
                'DELETE FROM site_aliases
                 WHERE domain = ?
                 AND site_id IN (SELECT id FROM sites WHERE deleted_at IS NOT NULL)',
                [$alias]
            );

            $this->database->execute(
                'INSERT INTO site_aliases (site_id, domain, created_at) VALUES (?, ?, ?)',
                [$siteId, $alias, date('Y-m-d H:i:s')]
            );
        }
    }
}
