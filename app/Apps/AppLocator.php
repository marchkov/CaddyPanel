<?php

namespace CaddyPanel\Apps;

class AppLocator
{
    public function __construct(
        private string $adminerPath,
        private string $filegatorPath
    ) {
    }

    public function adminer(): array
    {
        $entry = rtrim($this->adminerPath, '/\\') . '/adminer.php';

        return [
            'name' => 'Adminer',
            'path' => $this->adminerPath,
            'entry' => $entry,
            'installed' => is_file($entry),
        ];
    }

    public function filegator(): array
    {
        $path = rtrim($this->filegatorPath, '/\\');
        $entry = is_file($path . '/dist/index.php')
            ? $path . '/dist/index.php'
            : $path . '/index.php';

        return [
            'name' => 'FileGator',
            'path' => $this->filegatorPath,
            'entry' => $entry,
            'root' => '/var/www/sites',
            'installed' => is_file($entry),
        ];
    }
}
