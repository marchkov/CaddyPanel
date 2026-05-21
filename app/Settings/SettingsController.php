<?php

namespace CaddyPanel\Settings;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Database;
use CaddyPanel\Core\IpAccess;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Modules\ModuleService;
use CaddyPanel\Support\AuthGuard;

class SettingsController
{
    public function __construct(
        private SettingRepository $settings,
        private ModuleService $modules,
        private Database $database,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('settings', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();

        if ($request->method() === 'POST') {
            $this->handleUpdate($request);
        }

        Response::view('settings/index', ($this->viewData)([
            'modules' => $this->modules->all(),
            'settings' => $this->editableSettings(),
            'error' => null,
        ]));
    }

    private function handleUpdate(Request $request): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('settings/index', ($this->viewData)([
                'modules' => $this->modules->all(),
                'settings' => $this->editableSettings(),
                'error' => 'Invalid session token.',
            ]));
        }

        try {
            $this->updateEditableSettings($request);
        } catch (\InvalidArgumentException $exception) {
            Response::view('settings/index', ($this->viewData)([
                'modules' => $this->modules->all(),
                'settings' => $this->settingsFromRequest($request),
                'error' => $exception->getMessage(),
            ]));
        }

        foreach ($this->modules->all() as $module) {
            $name = $module['name'];
            $enabled = isset($_POST['module'][$name]);

            if ($name === 'settings') {
                $enabled = true;
            }

            $this->modules->setEnabled($name, $enabled);
        }

        $this->audit((int) $_SESSION['user']['id'], 'settings_update', 'success', 'Updated settings.', $request->ip());
        Response::redirect('/settings');
    }

    private function editableSettings(): array
    {
        return [
            'panel_domain' => $this->settings->get('panel_domain', 'localhost'),
            'admin_email' => $this->settings->get('admin_email', 'admin@example.com'),
            'ui_theme' => $this->settings->get('ui_theme', 'dark'),
            'default_php_version' => $this->settings->get('default_php_version', '8.4'),
            'default_php_fpm_socket' => $this->settings->get('default_php_fpm_socket', '/run/php/php8.4-fpm.sock'),
            'backup_retention_count' => $this->settings->get('backup_retention_count', '7'),
            'session_lifetime' => $this->settings->get('session_lifetime', '3600'),
            'security_ip_allowlist' => $this->settings->get('security_ip_allowlist', ''),
            'health_check_token' => $this->settings->get('health_check_token', ''),
            'updates_auto_check' => $this->settings->get('updates_auto_check', '1'),
            'updates_branch' => $this->settings->get('updates_branch', 'main'),
        ];
    }

    private function updateEditableSettings(Request $request): void
    {
        $panelDomain = strtolower(trim((string) $request->post('panel_domain', '')));
        $adminEmail = trim((string) $request->post('admin_email', ''));
        $theme = (string) $request->post('ui_theme', 'dark');
        $phpVersion = trim((string) $request->post('default_php_version', '8.4'));
        $phpSocket = trim((string) $request->post('default_php_fpm_socket', '/run/php/php8.4-fpm.sock'));
        $retentionCount = max(1, min(365, (int) $request->post('backup_retention_count', '7')));
        $sessionLifetime = max(300, min(86400, (int) $request->post('session_lifetime', '3600')));
        $ipAllowlist = trim((string) $request->post('security_ip_allowlist', ''));
        $healthToken = trim((string) $request->post('health_check_token', ''));
        $updatesBranch = trim((string) $request->post('updates_branch', 'main'));

        if ($panelDomain !== 'localhost' && preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $panelDomain) !== 1) {
            throw new \InvalidArgumentException('Invalid panel domain.');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid admin email.');
        }

        if (!in_array($theme, ['dark', 'light'], true)) {
            throw new \InvalidArgumentException('Invalid UI theme.');
        }

        if (preg_match('/^\d+\.\d+$/', $phpVersion) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP version.');
        }

        if (!str_starts_with($phpSocket, '/run/php/') || !str_ends_with($phpSocket, '-fpm.sock')) {
            throw new \InvalidArgumentException('Invalid PHP-FPM socket path.');
        }

        if (preg_match('/^[a-zA-Z0-9._\/-]+$/', $updatesBranch) !== 1) {
            throw new \InvalidArgumentException('Invalid updates branch.');
        }

        if (!IpAccess::validateAllowlist($ipAllowlist)) {
            throw new \InvalidArgumentException('Invalid IP allowlist. Use IPs or CIDR ranges separated by commas.');
        }

        if ($ipAllowlist !== '' && !IpAccess::isAllowed($request->ip(), $ipAllowlist)) {
            throw new \InvalidArgumentException('The IP allowlist does not include your current IP address.');
        }

        if ($healthToken !== '' && preg_match('/^[a-zA-Z0-9._~:-]{16,128}$/', $healthToken) !== 1) {
            throw new \InvalidArgumentException('Health check token must be 16-128 safe characters.');
        }

        $this->settings->set('panel_domain', $panelDomain);
        $this->settings->set('admin_email', $adminEmail);
        $this->settings->set('ui_theme', $theme);
        $this->settings->set('default_php_version', $phpVersion);
        $this->settings->set('default_php_fpm_socket', $phpSocket);
        $this->settings->set('backup_retention_count', (string) $retentionCount);
        $this->settings->set('session_lifetime', (string) $sessionLifetime);
        $this->settings->set('security_ip_allowlist', $ipAllowlist);
        $this->settings->set('health_check_token', $healthToken);
        $this->settings->set('updates_auto_check', !empty($_POST['updates_auto_check']) ? '1' : '0');
        $this->settings->set('updates_branch', $updatesBranch);
    }

    private function settingsFromRequest(Request $request): array
    {
        return [
            'panel_domain' => (string) $request->post('panel_domain', ''),
            'admin_email' => (string) $request->post('admin_email', ''),
            'ui_theme' => (string) $request->post('ui_theme', 'dark'),
            'default_php_version' => (string) $request->post('default_php_version', '8.4'),
            'default_php_fpm_socket' => (string) $request->post('default_php_fpm_socket', '/run/php/php8.4-fpm.sock'),
            'backup_retention_count' => (string) $request->post('backup_retention_count', '7'),
            'session_lifetime' => (string) $request->post('session_lifetime', '3600'),
            'security_ip_allowlist' => (string) $request->post('security_ip_allowlist', ''),
            'health_check_token' => (string) $request->post('health_check_token', ''),
            'updates_auto_check' => !empty($_POST['updates_auto_check']) ? '1' : '0',
            'updates_branch' => (string) $request->post('updates_branch', 'main'),
        ];
    }

    private function audit(int $userId, string $action, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [$userId, $action, 'settings', $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
