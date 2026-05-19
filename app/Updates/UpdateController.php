<?php

namespace CaddyPanel\Updates;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class UpdateController
{
    public function __construct(
        private UpdateService $updates,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('updates', ($this->viewData)());
        $this->guard->requireAdmin();

        Response::view('updates/index', ($this->viewData)([
            'status' => $this->updates->status(),
            'config' => $this->updates->config(),
            'error' => null,
            'result' => null,
        ]));
    }

    public function action(): void
    {
        $this->guard->requireModule('updates', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('updates/index', ($this->viewData)([
                'status' => $this->updates->status(),
                'config' => $this->updates->config(),
                'error' => 'Invalid session token.',
                'result' => null,
            ]));
        }

        try {
            $action = (string) $request->post('action', '');

            if ($action === 'save_config') {
                $this->updates->setConfig(
                    !empty($_POST['auto_check']),
                    trim((string) $request->post('branch', 'main')),
                    trim((string) $request->post('repository_url', ''))
                );
                Response::redirect('/updates');
            }

            if ($action === 'check') {
                $result = $this->updates->check((int) $_SESSION['user']['id'], $request->ip());
            } elseif ($action === 'apply') {
                $result = $this->updates->apply((int) $_SESSION['user']['id'], $request->ip());
            } else {
                throw new \InvalidArgumentException('Unknown update action.');
            }

            Response::view('updates/index', ($this->viewData)([
                'status' => $this->updates->status(),
                'config' => $this->updates->config(),
                'error' => null,
                'result' => $result,
            ]));
        } catch (\Throwable $exception) {
            Response::view('updates/index', ($this->viewData)([
                'status' => $this->updates->status(),
                'config' => $this->updates->config(),
                'error' => $exception->getMessage(),
                'result' => null,
            ]));
        }
    }
}
