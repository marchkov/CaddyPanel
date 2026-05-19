<?php

namespace CaddyPanel\Updates;

use CaddyPanel\Core\Database;
use CaddyPanel\Settings\SettingRepository;
use CaddyPanel\System\CommandRunner;

class UpdateService
{
    public function __construct(
        private CommandRunner $commands,
        private SettingRepository $settings,
        private Database $database,
        private string $repoPath
    ) {
    }

    public function status(): array
    {
        $raw = $this->settings->get('updates_last_status');

        if (!$raw) {
            return [
                'checked_at' => null,
                'result' => null,
            ];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['checked_at' => null, 'result' => $raw];
    }

    public function check(?int $userId, string $ipAddress): array
    {
        $branch = $this->settings->get('updates_branch', 'main') ?: 'main';
        $result = $this->commands->run('update-check', [
            'repo_path' => $this->repoPath,
            'branch' => $branch,
        ]);

        $payload = [
            'checked_at' => date('Y-m-d H:i:s'),
            'exit_code' => $result['exit_code'],
            'output' => $result['output'],
            'parsed' => json_decode($result['output'], true),
        ];

        $this->settings->set('updates_last_status', json_encode($payload, JSON_UNESCAPED_SLASHES));
        $this->audit($userId, 'update_check', $result['exit_code'] === 0 ? 'success' : 'failed', $result['output'], $ipAddress);

        return $payload;
    }

    public function apply(?int $userId, string $ipAddress): array
    {
        $branch = $this->settings->get('updates_branch', 'main') ?: 'main';
        $result = $this->commands->run('update-apply', [
            'repo_path' => $this->repoPath,
            'branch' => $branch,
        ]);

        $payload = [
            'applied_at' => date('Y-m-d H:i:s'),
            'exit_code' => $result['exit_code'],
            'output' => $result['output'],
        ];

        $this->settings->set('updates_last_apply', json_encode($payload, JSON_UNESCAPED_SLASHES));
        $this->audit($userId, 'update_apply', $result['exit_code'] === 0 ? 'success' : 'failed', $result['output'], $ipAddress);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException($result['output']);
        }

        return $payload;
    }

    public function setConfig(bool $autoCheck, string $branch): void
    {
        if (preg_match('/^[a-zA-Z0-9._\/-]+$/', $branch) !== 1) {
            throw new \InvalidArgumentException('Invalid branch name.');
        }

        $this->settings->set('updates_auto_check', $autoCheck ? '1' : '0');
        $this->settings->set('updates_branch', $branch);
    }

    public function config(): array
    {
        return [
            'auto_check' => $this->settings->get('updates_auto_check', '1') === '1',
            'branch' => $this->settings->get('updates_branch', 'main') ?: 'main',
        ];
    }

    private function audit(?int $userId, string $action, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [$userId, $action, 'updates', $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
