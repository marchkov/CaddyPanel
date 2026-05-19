<?php

namespace CaddyPanel\Users;

use CaddyPanel\Core\Database;

class UserService
{
    public function __construct(
        private UserRepository $users,
        private Database $database
    ) {
    }

    public function all(): array
    {
        return $this->users->all();
    }

    public function create(array $input, int $actorId, string $ipAddress): int
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $role = (string) ($input['role'] ?? 'manager');

        if (preg_match('/^[a-z0-9_]{3,32}$/', $username) !== 1) {
            throw new \InvalidArgumentException('Username must be 3-32 chars: a-z, 0-9, underscore.');
        }

        if (!in_array($role, ['admin', 'manager'], true)) {
            throw new \InvalidArgumentException('Invalid role.');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        if ($this->users->findByUsername($username)) {
            throw new \InvalidArgumentException('Username already exists.');
        }

        $id = $this->users->create($username, password_hash($password, PASSWORD_DEFAULT), $role);
        $this->audit($actorId, 'user_create', $id, 'success', 'Created user ' . $username, $ipAddress);

        return $id;
    }

    public function setActive(int $id, bool $active, int $actorId, string $ipAddress): void
    {
        $user = $this->users->find($id);

        if (!$user) {
            throw new \InvalidArgumentException('User not found.');
        }

        if ($id === $actorId && !$active) {
            throw new \InvalidArgumentException('You cannot deactivate your own account.');
        }

        $this->users->setActive($id, $active);
        $this->audit($actorId, $active ? 'user_activate' : 'user_deactivate', $id, 'success', $user['username'], $ipAddress);
    }

    public function resetPassword(int $id, string $password, int $actorId, string $ipAddress): void
    {
        $user = $this->users->find($id);

        if (!$user) {
            throw new \InvalidArgumentException('User not found.');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        $this->users->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
        $this->audit($actorId, 'user_password_reset', $id, 'success', $user['username'], $ipAddress);
    }

    private function audit(int $actorId, string $action, int $targetId, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$actorId, $action, 'user', $targetId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
