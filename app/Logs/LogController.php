<?php

namespace CaddyPanel\Logs;

use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Sites\SiteRepository;
use CaddyPanel\Support\AuthGuard;

class LogController
{
    public function __construct(
        private AuditLogRepository $auditLogs,
        private SiteRepository $sites,
        private SiteLogReader $siteLogs,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('logs', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $user = $_SESSION['user'] ?? [];

        Response::view('logs/index', ($this->viewData)([
            'sites' => $this->sites->all(),
            'logs' => ($user['role'] ?? null) === 'admin' ? $this->auditLogs->latest() : [],
            'showAudit' => ($user['role'] ?? null) === 'admin',
        ]));
    }

    public function site(string $id): void
    {
        $this->guard->requireModule('logs', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $site = $this->sites->find((int) $id);

        if (!$site || $site['deleted_at'] !== null) {
            Response::notFound();
        }

        $request = new Request();
        $type = (string) $request->query('type', 'access');

        if (!in_array($type, ['access', 'error'], true)) {
            $type = 'access';
        }

        Response::view('logs/site', ($this->viewData)([
            'site' => $site,
            'type' => $type,
            'log' => $this->siteLogs->read($site, $type),
        ]));
    }
}
