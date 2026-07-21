<?php

namespace CaddyPanel\PhpVersions;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class PhpVersionController
{
    public function __construct(
        private PhpVersionService $versions,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('php_versions', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();
        $error = null;

        if ($request->method() === 'POST') {
            $error = $this->handleAction($request);
        }

        Response::view('php-versions/index', ($this->viewData)([
            'overview' => $this->versions->overview(),
            'error' => $error,
        ]));
    }

    private function handleAction(Request $request): ?string
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            return 'Invalid session token.';
        }

        try {
            $action = (string) $request->post('action', '');

            if ($action === 'refresh') {
                $this->versions->refresh((int) $_SESSION['user']['id'], $request->ip());
                Response::redirect('/php-versions');
            }

            if ($action === 'set_default') {
                $this->versions->setDefault((string) $request->post('version', ''), (int) $_SESSION['user']['id'], $request->ip());
                Response::redirect('/php-versions');
            }

            if ($action === 'mark_manual') {
                $this->versions->markManual((string) $request->post('version', ''), (int) $_SESSION['user']['id'], $request->ip());
                Response::redirect('/php-versions');
            }

            return 'Unknown action.';
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }
}
