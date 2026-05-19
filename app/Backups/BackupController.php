<?php

namespace CaddyPanel\Backups;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Sites\SiteService;
use CaddyPanel\Support\AuthGuard;

class BackupController
{
    public function __construct(
        private BackupService $backups,
        private BackupJobService $jobs,
        private SiteService $sites,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        Response::view('backups/index', ($this->viewData)([
            'backups' => $this->backups->all(),
            'jobs' => $this->jobs->all(),
            'sites' => $this->sites->all(),
            'error' => null,
        ]));
    }

    public function create(): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('backups/index', ($this->viewData)([
                'backups' => $this->backups->all(),
                'jobs' => $this->jobs->all(),
                'sites' => $this->sites->all(),
                'error' => 'Invalid session token.',
            ]));
        }

        try {
            $this->backups->createForSite((int) $request->post('site_id'), (int) $_SESSION['user']['id'], $request->ip());
            Response::redirect('/backups');
        } catch (\Throwable $exception) {
            Response::view('backups/index', ($this->viewData)([
                'backups' => $this->backups->all(),
                'jobs' => $this->jobs->all(),
                'sites' => $this->sites->all(),
                'error' => $exception->getMessage(),
            ]));
        }
    }

    public function createJob(): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('backups/index', ($this->viewData)([
                'backups' => $this->backups->all(),
                'jobs' => $this->jobs->all(),
                'sites' => $this->sites->all(),
                'error' => 'Invalid session token.',
            ]));
        }

        try {
            $this->jobs->create($_POST, (int) $_SESSION['user']['id'], $request->ip());
            Response::redirect('/backups');
        } catch (\Throwable $exception) {
            Response::view('backups/index', ($this->viewData)([
                'backups' => $this->backups->all(),
                'jobs' => $this->jobs->all(),
                'sites' => $this->sites->all(),
                'error' => $exception->getMessage(),
            ]));
        }
    }
}
