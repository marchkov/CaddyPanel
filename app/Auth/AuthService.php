<?php

namespace CaddyPanel\Auth;

use CaddyPanel\Core\Database;

class AuthService
{
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_WINDOW_SECONDS = 600;

    public function __construct(private Database $database)
    {
        $this->ensureLoginAttemptsTable();
    }

    public function attempt(string $username, string $password, string $ipAddress): bool
    {
        $username = trim($username);
        $attemptKey = strtolower($username);

        if ($this->isRateLimited($attemptKey, $ipAddress)) {
            $this->recordLoginAttempt($attemptKey, $ipAddress, false);
            $this->audit(null, 'login_rate_limited', 'auth', null, 'failed', 'Rate limited login for ' . $username, $ipAddress);
            return false;
        }

        $user = $this->database->fetch(
            'SELECT * FROM users WHERE username = ? AND is_active = 1',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordLoginAttempt($attemptKey, $ipAddress, false);
            $this->audit(null, 'login_failed', 'auth', null, 'failed', 'Invalid credentials for ' . $username, $ipAddress);
            return false;
        }

        $this->recordLoginAttempt($attemptKey, $ipAddress, true);
        $this->clearFailedLoginAttempts($attemptKey, $ipAddress);
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

    private function isRateLimited(string $username, string $ipAddress): bool
    {
        $this->pruneOldLoginAttempts();
        $cutoff = date('Y-m-d H:i:s', time() - self::LOGIN_WINDOW_SECONDS);
        $row = $this->database->fetch(
            'SELECT COUNT(*) AS count FROM login_attempts
             WHERE username = ?
             AND ip_address = ?
             AND success = 0
             AND attempted_at >= ?',
            [$username, $ipAddress, $cutoff]
        );

        return (int) ($row['count'] ?? 0) >= self::LOGIN_ATTEMPT_LIMIT;
    }

    private function recordLoginAttempt(string $username, string $ipAddress, bool $success): void
    {
        $this->database->execute(
            'INSERT INTO login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, ?, ?)',
            [$username, $ipAddress, $success ? 1 : 0, date('Y-m-d H:i:s')]
        );
    }

    private function clearFailedLoginAttempts(string $username, string $ipAddress): void
    {
        $this->database->execute(
            'DELETE FROM login_attempts WHERE username = ? AND ip_address = ? AND success = 0',
            [$username, $ipAddress]
        );
    }

    private function pruneOldLoginAttempts(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $this->database->execute('DELETE FROM login_attempts WHERE attempted_at < ?', [$cutoff]);
    }

    private function ensureLoginAttemptsTable(): void
    {
        $this->database->execute(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                success INTEGER NOT NULL DEFAULT 0,
                attempted_at TEXT NOT NULL
            )'
        );
        $this->database->execute(
            'CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup
             ON login_attempts(username, ip_address, attempted_at)'
        );
    }
}
