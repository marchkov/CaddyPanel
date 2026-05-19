<?php

namespace CaddyPanel\Sites;

use CaddyPanel\System\CommandRunner;

class SiteProvisioner
{
    public function __construct(private CommandRunner $commands)
    {
    }

    public function createDirectories(array $site): string
    {
        $result = $this->commands->run('site-create-dirs', [
            'domain' => $site['domain'],
            'type' => $site['type'],
            'root_path' => $site['root_path'],
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to create site directories: ' . $result['output']);
        }

        return $result['output'];
    }

    public function deleteFiles(array $site): string
    {
        $result = $this->commands->run('site-delete-files', [
            'domain' => $site['domain'],
            'root_path' => $site['root_path'],
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to delete site files: ' . $result['output']);
        }

        return $result['output'];
    }
}
