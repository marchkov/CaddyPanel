<?php

namespace CaddyPanel\Security;

class SecretKey
{
    public static function load(string $path, bool $createIfMissing = false): string
    {
        if (!is_file($path)) {
            if (!$createIfMissing) {
                throw new \RuntimeException('Secret key not found.');
            }

            $directory = dirname($path);

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            file_put_contents($path, bin2hex(random_bytes(32)));
        }

        $key = trim((string) file_get_contents($path));

        if ($key === '') {
            throw new \RuntimeException('Secret key is empty.');
        }

        return hash('sha256', $key, true);
    }
}
