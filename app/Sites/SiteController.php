<?php

namespace CaddyPanel\Sites;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\PhpVersions\PhpVersionService;
use CaddyPanel\Support\AuthGuard;

class SiteController
{
    public function __construct(
        private SiteService $sites,
        private PhpVersionService $phpVersions,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('sites', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        Response::view('sites/index', ($this->viewData)([
            'sites' => $this->sites->all(),
        ]));
    }

    public function create(): void
    {
        $this->guard->requireModule('sites', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if ($request->method() === 'POST') {
            $this->handleCreate($request);
        }

        Response::view('sites/create', ($this->viewData)([
            'error' => null,
            'old' => [],
            'phpVersions' => $this->phpVersions->all(),
        ]));
    }

    public function show(string $id): void
    {
        $this->guard->requireModule('sites', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $site = $this->sites->findWithAliases((int) $id);

        if (!$site || $site['deleted_at'] !== null) {
            Response::notFound();
        }

        Response::view('sites/show', ($this->viewData)([
            'site' => $site,
        ]));
    }

    public function delete(string $id): void
    {
        $this->guard->requireModule('sites', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();
        $site = $this->sites->findWithAliases((int) $id);

        if (!$site || $site['deleted_at'] !== null) {
            Response::notFound();
        }

        if ($request->method() === 'POST') {
            $this->handleDelete($request, (int) $id, $site);
        }

        Response::view('sites/delete', ($this->viewData)([
            'site' => $site,
            'error' => null,
        ]));
    }

    private function handleCreate(Request $request): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('sites/create', ($this->viewData)([
                'error' => 'Invalid session token.',
                'old' => $_POST,
                'phpVersions' => $this->phpVersions->all(),
            ]));
        }

        try {
            $siteId = $this->sites->create($_POST, (int) $_SESSION['user']['id'], $request->ip());
            Response::redirect('/sites/' . $siteId);
        } catch (\Throwable $exception) {
            Response::view('sites/create', ($this->viewData)([
                'error' => $exception->getMessage(),
                'old' => $_POST,
                'phpVersions' => $this->phpVersions->all(),
            ]));
        }
    }

    private function handleDelete(Request $request, int $siteId, array $site): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('sites/delete', ($this->viewData)([
                'site' => $site,
                'error' => 'Invalid session token.',
            ]));
        }

        try {
            $this->sites->delete($siteId, $_POST, (int) $_SESSION['user']['id'], $request->ip());
            Response::redirect('/sites');
        } catch (\Throwable $exception) {
            Response::view('sites/delete', ($this->viewData)([
                'site' => $site,
                'error' => $exception->getMessage(),
            ]));
        }
    }
}
