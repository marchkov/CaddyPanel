<?php

namespace CaddyPanel\Restore;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class RestoreController
{
    public function __construct(
        private RestoreService $restore,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        Response::redirect('/backups');
    }

    public function show(string $id): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        try {
            $data = $this->restore->inspect((int) $id);
            Response::view('restore/show', ($this->viewData)([
                'backup' => $data['backup'],
                'inspection' => $data['inspection'],
                'error' => null,
                'result' => null,
            ]));
        } catch (\Throwable $exception) {
            Response::view('restore/index', ($this->viewData)([
                'backups' => $this->restore->availableBackups(),
                'error' => $exception->getMessage(),
            ]));
        }
    }

    public function apply(string $id): void
    {
        $this->guard->requireModule('backups', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            $data = $this->restore->inspect((int) $id);
            Response::view('restore/show', ($this->viewData)([
                'backup' => $data['backup'],
                'inspection' => $data['inspection'],
                'error' => 'Invalid session token.',
                'result' => null,
            ]));
        }

        try {
            $result = $this->restore->restore((int) $id, $_POST, (int) $_SESSION['user']['id'], $request->ip());
            $data = $this->restore->inspect((int) $id);
            Response::view('restore/show', ($this->viewData)([
                'backup' => $data['backup'],
                'inspection' => $data['inspection'],
                'error' => null,
                'result' => $result,
            ]));
        } catch (\Throwable $exception) {
            $data = $this->restore->inspect((int) $id);
            Response::view('restore/show', ($this->viewData)([
                'backup' => $data['backup'],
                'inspection' => $data['inspection'],
                'error' => $exception->getMessage(),
                'result' => null,
            ]));
        }
    }
}
