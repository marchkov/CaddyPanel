<?php

namespace CaddyPanel\Core;

class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function view(string $template, array $data = []): never
    {
        extract($data, EXTR_SKIP);

        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';

        if (!is_file($templatePath)) {
            self::error('Template not found: ' . $template, 500);
        }

        require $templatePath;
        exit;
    }

    public static function error(string $message, int $status = 500): never
    {
        http_response_code($status);
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        exit;
    }

    public static function notFound(): never
    {
        self::error('Not found', 404);
    }
}
