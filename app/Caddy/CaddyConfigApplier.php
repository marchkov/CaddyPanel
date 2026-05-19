<?php

namespace CaddyPanel\Caddy;

use CaddyPanel\System\CommandRunner;

class CaddyConfigApplier
{
    public function __construct(private CommandRunner $commands)
    {
    }

    public function applySiteConfig(string $domain, string $pendingPath, string $finalPath): string
    {
        $result = $this->commands->run('caddy-apply-site-config', [
            'domain' => $domain,
            'pending_path' => $pendingPath,
            'final_path' => $finalPath,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to apply Caddy config: ' . $result['output']);
        }

        return $result['output'];
    }

    public function disableSite(string $domain, string $configPath): string
    {
        $result = $this->commands->run('caddy-disable-site', [
            'domain' => $domain,
            'config_path' => $configPath,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to disable Caddy site: ' . $result['output']);
        }

        return $result['output'];
    }
}
