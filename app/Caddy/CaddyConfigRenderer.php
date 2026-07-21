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
        return $this->renderTemplate($site, $aliases);
    }

    public function renderPreservingExisting(array $site, array $aliases, ?string $existingConfig): string
    {
        if ($existingConfig === null || trim($existingConfig) === '') {
            return $this->renderTemplate($site, $aliases);
        }

        if ($this->siteBlockCount($existingConfig) !== 1) {
            return $this->renderTemplate($site, $aliases);
        }

        $hosts = $this->hosts($site, $aliases);
        $config = $existingConfig;

        $config = preg_replace('/^([^{\n]+)\{\s*$/m', implode(', ', $hosts) . ' {', $config, 1) ?? $config;
        $config = preg_replace('/^\s*root\s+\*\s+.+$/m', '    root * ' . $site['public_path'], $config, 1) ?? $config;

        $phpLine = '    php_fastcgi unix/' . ($site['php_fpm_socket'] ?: ($this->defaults['php_fpm_socket'] ?? '/run/php/php8.4-fpm.sock'));

        if ($site['type'] === 'php') {
            if (preg_match('/^\s*php_fastcgi\s+.+$/m', $config)) {
                $config = preg_replace('/^\s*php_fastcgi\s+.+$/m', $phpLine, $config, 1) ?? $config;
            } elseif (preg_match('/^\s*file_server\s*$/m', $config)) {
                $config = preg_replace('/^\s*file_server\s*$/m', $phpLine . "\n    file_server", $config, 1) ?? $config;
            } else {
                $config = preg_replace('/^\s*encode\s+.+$/m', "$0\n\n" . $phpLine, $config, 1) ?? $config;
            }
        } else {
            $config = preg_replace('/^\s*php_fastcgi\s+.+\n?/m', '', $config) ?? $config;
        }

        $accessLog = rtrim($site['logs_path'], '/') . '/access.log';
        $config = preg_replace('/^\s*output\s+file\s+.+$/m', '        output file ' . $accessLog . ' {', $config, 1) ?? $config;

        return rtrim($config) . "\n";
    }

    private function siteBlockCount(string $config): int
    {
        preg_match_all('/^[^#\s][^{\n]*\{\s*$/m', $config, $matches);

        return count($matches[0] ?? []);
    }

    public function writeConfig(array $site, string $config, string $suffix = '.caddy.pending'): string
    {
        if (!is_dir($this->generatedPath)) {
            mkdir($this->generatedPath, 0775, true);
        }

        $path = rtrim($this->generatedPath, '/') . '/' . $site['domain'] . $suffix;
        file_put_contents($path, $config);

        return $path;
    }

    private function renderTemplate(array $site, array $aliases): string
    {
        $templateFile = $site['type'] === 'php'
            ? $this->templatePath . '/site-php.caddy.tpl'
            : $this->templatePath . '/site-static.caddy.tpl';

        if (!is_file($templateFile)) {
            throw new \RuntimeException('Caddy template not found: ' . $templateFile);
        }

        $hosts = $this->hosts($site, $aliases);

        $replacements = [
            '{hosts}' => implode(', ', $hosts),
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
        return $this->writeConfig($site, $this->render($site, $aliases));
    }

    private function hosts(array $site, array $aliases): array
    {
        return array_values(array_unique(array_merge([$site['domain']], $this->normalizeAliases($aliases))));
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
