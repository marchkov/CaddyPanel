<?php

namespace CaddyPanel\Core;

class SessionManager
{
    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $params = session_get_cookie_params();

        if (session_id() !== '' && ini_get('session.use_cookies')) {
            $cookieOptions = [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ];

            if (!empty($params['domain'])) {
                $cookieOptions['domain'] = $params['domain'];
            }

            setcookie(session_name(), '', $cookieOptions);
        }

        session_destroy();
    }
}
