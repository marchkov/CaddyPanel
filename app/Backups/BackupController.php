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
            $this->backups->queueForSite((int) $request->post('site_id'), (int) $_SESSION['user']['id'], $request->ip());
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

    public function download(string $id): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        try {
            $download = $this->backups->downloadable((int) $id);
        } catch (\Throwable $exception) {
            Response::view('backups/index', ($this->viewData)([
                'backups' => $this->backups->all(),
                'jobs' => $this->jobs->all(),
                'sites' => $this->sites->all(),
                'error' => $exception->getMessage(),
            ]));
        }

        header('Content-Type: application/gzip');
        header('Content-Length: ' . (string) $download['size']);
        header('Content-Disposition: attachment; filename="' . addcslashes($download['name'], '"\\') . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($download['file']);
        exit;
    }

    public function delete(string $id): void
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
            $this->backups->delete((int) $id, (int) $_SESSION['user']['id'], $request->ip());
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

    public function retry(string $id): void
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
            $this->backups->retry((int) $id, (int) $_SESSION['user']['id'], $request->ip());
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
