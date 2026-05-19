<?php

namespace CaddyPanel\PhpVersions;

use CaddyPanel\Core\Database;

class PhpVersionRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT * FROM php_versions ORDER BY version DESC');
    }

    public function findByVersion(string $version): ?array
    {
        return $this->database->fetch('SELECT * FROM php_versions WHERE version = ?', [$version]);
    }

    public function replaceDetected(array $versions): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->transaction(function () use ($versions, $now): void {
            $this->database->execute('DELETE FROM php_versions');

            foreach ($versions as $version) {
                $this->database->execute(
                    'INSERT INTO php_versions (version, fpm_socket, is_default, detected_at) VALUES (?, ?, ?, ?)',
                    [$version['version'], $version['fpm_socket'], !empty($version['is_default']) ? 1 : 0, $now]
                );
            }
        });
    }

    public function setDefault(string $version): void
    {
        $this->database->transaction(function () use ($version): void {
            $this->database->execute('UPDATE php_versions SET is_default = 0');
            $this->database->execute('UPDATE php_versions SET is_default = 1 WHERE version = ?', [$version]);
        });
    }
}
