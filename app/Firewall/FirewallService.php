<?php

namespace CaddyPanel\Firewall;

use CaddyPanel\Core\Database;
use CaddyPanel\System\CommandRunner;

class FirewallService
{
    private const ACTIONS = ['status', 'rules', 'allow', 'deny', 'delete', 'enable', 'disable'];

    public function __construct(
        private CommandRunner $commands,
        private Database $database
    ) {
    }

    public function status(): array
    {
        return $this->commands->run('firewall-task', ['action' => 'status']);
    }

    public function rules(): array
    {
        return $this->commands->run('firewall-task', ['action' => 'rules']);
    }

    public function run(array $input, int $userId, string $ipAddress): array
    {
        $action = (string) ($input['action'] ?? '');

        if (!in_array($action, self::ACTIONS, true) || in_array($action, ['status', 'rules'], true)) {
            throw new \InvalidArgumentException('Unknown firewall action.');
        }

        $args = ['action' => $action];

        if (in_array($action, ['allow', 'deny'], true)) {
            $args['port'] = $this->assertPort((string) ($input['port'] ?? ''));
            $args['proto'] = $this->assertProtocol((string) ($input['proto'] ?? 'tcp'));
        }

        if ($action === 'delete') {
            $args['rule'] = $this->assertRule((string) ($input['rule'] ?? ''));
        }

        $result = $this->commands->run('firewall-task', $args);
        $this->audit($userId, 'firewall_' . $action, $result, $ipAddress);

        return $result;
    }

    private function assertPort(string $port): string
    {
        if (preg_match('/^\d+$/', $port) !== 1 || (int) $port < 1 || (int) $port > 65535) {
            throw new \InvalidArgumentException('Invalid port.');
        }

        return $port;
    }

    private function assertProtocol(string $protocol): string
    {
        if (!in_array($protocol, ['tcp', 'udp'], true)) {
            throw new \InvalidArgumentException('Invalid protocol.');
        }

        return $protocol;
    }

    private function assertRule(string $rule): string
    {
        if (preg_match('/^\d+$/', $rule) !== 1 || (int) $rule < 1 || (int) $rule > 999) {
            throw new \InvalidArgumentException('Invalid rule number.');
        }

        return $rule;
    }

    private function audit(int $userId, string $action, array $result, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                'firewall',
                (int) ($result['exit_code'] ?? 1) === 0 ? 'success' : 'failed',
                substr((string) ($result['output'] ?? ''), 0, 2000),
                $ipAddress,
                date('Y-m-d H:i:s'),
            ]
        );
    }
}
