<?php

namespace CaddyPanel\Auth;

use CaddyPanel\Core\Database;

class AuthService
{
    public function __construct(private Database $database)
    {
    }

    public function attempt(string $username, string $password, string $ipAddress): bool
    {
        $user = $this->database->fetch(
            'SELECT * FROM users WHERE username = ? AND is_active = 1',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->audit(null, 'login_failed', 'auth', null, 'failed', 'Invalid credentials for ' . $username, $ipAddress);
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];

        $this->audit((int) $user['id'], 'login', 'auth', null, 'success', null, $ipAddress);

        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function isAdmin(): bool
    {
        return ($_SESSION['user']['role'] ?? null) === 'admin';
    }

    public function isManagerOrAdmin(): bool
    {
        return in_array($_SESSION['user']['role'] ?? null, ['admin', 'manager'], true);
    }

    public function logout(string $ipAddress): void
    {
        $userId = $_SESSION['user']['id'] ?? null;

        if ($userId) {
            $this->audit((int) $userId, 'logout', 'auth', null, 'success', null, $ipAddress);
        }

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    private function audit(?int $userId, string $action, ?string $targetType, ?int $targetId, string $status, ?string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, $targetType, $targetId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
