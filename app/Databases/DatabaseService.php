<?php

namespace CaddyPanel\Databases;

use CaddyPanel\Core\Database;
use CaddyPanel\Security\Encryptor;

class DatabaseService
{
    public function __construct(
        private DatabaseRepository $databases,
        private Database $database,
        private DatabaseProvisioner $provisioner,
        private Encryptor $encryptor
    ) {
    }

    public function all(): array
    {
        return $this->databases->all();
    }

    public function find(int $id): ?array
    {
        return $this->databases->find($id);
    }

    public function create(array $input, int $userId, string $ipAddress): array
    {
        $siteId = !empty($input['site_id']) ? (int) $input['site_id'] : null;
        $name = strtolower(trim((string) ($input['name'] ?? '')));

        if ($name === '') {
            $domain = trim((string) ($input['domain_hint'] ?? 'database'));
            $name = $this->suggestName($domain);
        }

        $this->assertName($name);

        $existing = $this->databases->findAnyByName($name);

        if ($existing && $existing['deleted_at'] === null) {
            throw new \InvalidArgumentException('Database name already exists.');
        }

        $username = $name;
        $password = $this->generatePassword();
        $now = date('Y-m-d H:i:s');
        $message = 'Created database record.';

        try {
            $message .= ' Provisioning: ' . $this->provisioner->create($name, $username, $password);
        } catch (\Throwable $exception) {
            $this->audit($userId, 'database_create', null, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }

        $data = [
            'site_id' => $siteId,
            'name' => $name,
            'username' => $username,
            'password_encrypted' => $this->encryptor->encrypt($password),
            'host' => 'localhost',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $id = $existing && $existing['deleted_at'] !== null
            ? $this->databases->restoreDeleted((int) $existing['id'], $data)
            : $this->databases->create($data);

        $this->audit($userId, 'database_create', $id, 'success', $message, $ipAddress);

        return [
            'id' => $id,
            'name' => $name,
            'username' => $username,
            'password' => $password,
        ];
    }

    public function revealPassword(int $id, int $userId, string $ipAddress): string
    {
        $database = $this->databases->find($id);

        if (!$database || $database['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Database not found.');
        }

        $this->audit($userId, 'database_password_show', $id, 'success', 'Database password revealed.', $ipAddress);

        return $this->encryptor->decrypt($database['password_encrypted']);
    }

    public function auditPasswordRevealFailure(int $id, int $userId, string $ipAddress): void
    {
        $this->audit($userId, 'database_password_show', $id, 'failed', 'Current password confirmation failed.', $ipAddress);
    }

    public function attachToSite(int $id, int $siteId, int $userId, string $ipAddress): void
    {
        $database = $this->databases->find($id);

        if (!$database || $database['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Database not found.');
        }

        if (!$this->siteExists($siteId)) {
            throw new \InvalidArgumentException('Site not found.');
        }

        $this->databases->updateSite($id, $siteId);
        $this->audit($userId, 'database_attach_site', $id, 'success', 'Attached database to site #' . $siteId . '.', $ipAddress);
    }

    public function detachFromSite(int $id, int $userId, string $ipAddress): void
    {
        $database = $this->databases->find($id);

        if (!$database || $database['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Database not found.');
        }

        $this->databases->updateSite($id, null);
        $this->audit($userId, 'database_detach_site', $id, 'success', 'Detached database from site.', $ipAddress);
    }

    public function delete(int $id, int $userId, string $ipAddress): void
    {
        $database = $this->databases->find($id);

        if (!$database || $database['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Database not found.');
        }

        try {
            $message = 'Provisioning: ' . $this->provisioner->delete($database['name'], $database['username']);
        } catch (\Throwable $exception) {
            $this->audit($userId, 'database_delete', $id, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }

        $this->databases->markDeleted($id);
        $this->audit($userId, 'database_delete', $id, 'success', $message, $ipAddress);
    }

    private function assertName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,11}$/', $name) !== 1) {
            throw new \InvalidArgumentException('Database name must match ^[a-z][a-z0-9_]{0,11}$');
        }
    }

    private function suggestName(string $domain): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/', '', $domain) ?: 'database');

        if (!preg_match('/^[a-z]/', $base)) {
            $base = 'db' . $base;
        }

        $base = substr($base, 0, 8);
        $suffix = substr(sha1($domain), 0, 3);

        return substr($base . '_' . $suffix, 0, 12);
    }

    private function generatePassword(int $length = 24): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private function siteExists(int $siteId): bool
    {
        return $this->database->fetch(
            'SELECT id FROM sites WHERE id = ? AND deleted_at IS NULL',
            [$siteId]
        ) !== null;
    }

    private function audit(int $userId, string $action, ?int $databaseId, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, 'database', $databaseId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
