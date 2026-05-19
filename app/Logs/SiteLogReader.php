<?php

namespace CaddyPanel\Logs;

class SiteLogReader
{
    private const MAX_BYTES = 262144;
    private const MAX_LINES = 500;

    public function read(array $site, string $type): array
    {
        $path = $this->path($site, $type);

        if (!is_file($path)) {
            return [
                'type' => $type,
                'path' => $path,
                'exists' => false,
                'size' => null,
                'content' => '',
                'message' => 'Log file is not available yet.',
            ];
        }

        $size = filesize($path);
        $offset = max(0, (int) $size - self::MAX_BYTES);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open log file.');
        }

        try {
            if ($offset > 0) {
                fseek($handle, $offset);
            }

            $content = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $content) ?: [];

        if ($offset > 0 && $lines !== []) {
            array_shift($lines);
        }

        $lines = array_slice($lines, -self::MAX_LINES);

        return [
            'type' => $type,
            'path' => $path,
            'exists' => true,
            'size' => $size,
            'content' => implode(PHP_EOL, $lines),
            'message' => $offset > 0 ? 'Showing the last ' . self::MAX_LINES . ' lines.' : 'Showing full log.',
        ];
    }

    private function path(array $site, string $type): string
    {
        $file = match ($type) {
            'error' => 'error.log',
            default => 'access.log',
        };

        return rtrim((string) $site['logs_path'], '/\\') . '/' . $file;
    }
}
