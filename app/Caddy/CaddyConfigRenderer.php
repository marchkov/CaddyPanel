<?php

namespace CaddyPanel\Caddy;

class CaddyConfigRenderer
{
    public function __construct(
        private string $templatePath,
        private string $generatedPath,
        private array $defaults = []
    ) {
    }

    public function render(array $site, array $aliases): string
    {
        $templateFile = $site['type'] === 'php'
            ? $this->templatePath . '/site-php.caddy.tpl'
            : $this->templatePath . '/site-static.caddy.tpl';

        if (!is_file($templateFile)) {
            throw new \RuntimeException('Caddy template not found: ' . $templateFile);
        }

        $hosts = array_merge([$site['domain']], $this->normalizeAliases($aliases));

        $replacements = [
            '{hosts}' => implode(', ', array_unique($hosts)),
            '{public_path}' => $site['public_path'],
            '{php_fpm_socket}' => $site['php_fpm_socket'] ?: ($this->defaults['php_fpm_socket'] ?? '/run/php/php8.4-fpm.sock'),
            '{access_log}' => rtrim($site['logs_path'], '/') . '/access.log',
            '{roll_size}' => $this->defaults['roll_size'] ?? '10MB',
            '{roll_keep}' => (string) ($this->defaults['roll_keep'] ?? '10'),
            '{roll_keep_for}' => $this->defaults['roll_keep_for'] ?? '720h',
        ];

        return strtr(file_get_contents($templateFile), $replacements);
    }

    public function writePreview(array $site, array $aliases): string
    {
        if (!is_dir($this->generatedPath)) {
            mkdir($this->generatedPath, 0775, true);
        }

        $config = $this->render($site, $aliases);
        $path = rtrim($this->generatedPath, '/') . '/' . $site['domain'] . '.caddy.pending';
        file_put_contents($path, $config);

        return $path;
    }

    private function normalizeAliases(array $aliases): array
    {
        $hosts = [];

        foreach ($aliases as $alias) {
            if (is_array($alias) && isset($alias['domain'])) {
                $hosts[] = (string) $alias['domain'];
                continue;
            }

            if (is_string($alias)) {
                $hosts[] = $alias;
            }
        }

        return $hosts;
    }
}
