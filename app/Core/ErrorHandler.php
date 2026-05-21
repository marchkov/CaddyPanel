<?php

namespace CaddyPanel\Core;

class ErrorHandler
{
    public static function register(string $logDirectory, bool $production): void
    {
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        $logFile = rtrim($logDirectory, '/\\') . '/panel-error.log';
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
        ini_set('display_errors', $production ? '0' : '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logFile): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            self::write($logFile, 'php_error', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);

            return false;
        });

        set_exception_handler(static function (\Throwable $exception) use ($logFile, $production): void {
            self::write($logFile, 'uncaught_exception', [
                'type' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            http_response_code(500);
            echo $production ? 'Internal server error' : htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8');
            exit;
        });

        register_shutdown_function(static function () use ($logFile, $production): void {
            $error = error_get_last();

            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::write($logFile, 'fatal_error', $error);

            if (!headers_sent()) {
                http_response_code(500);
                echo $production ? 'Internal server error' : htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8');
            }
        });
    }

    private static function write(string $logFile, string $type, array $context): void
    {
        $entry = [
            'time' => date(DATE_ATOM),
            'type' => $type,
            'context' => $context,
        ];

        error_log(json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logFile);
    }
}
