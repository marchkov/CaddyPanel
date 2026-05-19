<?php

namespace CaddyPanel\Support;

use CaddyPanel\Auth\AuthService;
use CaddyPanel\Core\Response;
use CaddyPanel\Modules\ModuleService;

class AuthGuard
{
    public function __construct(
        private AuthService $auth,
        private ModuleService $modules
    ) {
    }

    public function requireLogin(): void
    {
        if (!$this->auth->check()) {
            Response::redirect('/login');
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();

        if (!$this->auth->isAdmin()) {
            Response::error('Forbidden', 403);
        }
    }

    public function requireManagerOrAdmin(): void
    {
        $this->requireLogin();

        if (!$this->auth->isManagerOrAdmin()) {
            Response::error('Forbidden', 403);
        }
    }

    public function requireModule(string $moduleName, array $viewData = []): void
    {
        $this->requireLogin();

        if (!$this->modules->isEnabled($moduleName)) {
            Response::view('errors/module-disabled', $viewData);
        }
    }
}
