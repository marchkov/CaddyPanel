<?php

namespace CaddyPanel\Core;

class SessionGuard
{
    public static function enforce(int $lifetimeSeconds, string $path): void
    {
        if (empty($_SESSION['user']['id'])) {
            return;
        }

        $now = time();
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);

        if ($lifetimeSeconds > 0 && ($now - $lastActivity) > $lifetimeSeconds) {
            $_SESSION = [];

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            if ($path === '/auth/check') {
                http_response_code(401);
                exit;
            }

            Response::redirect('/login');
        }

        $_SESSION['last_activity'] = $now;
    }
}
