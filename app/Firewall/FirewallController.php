<?php

namespace CaddyPanel\Firewall;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class FirewallController
{
    public function __construct(
        private FirewallService $firewall,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('firewall', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();
        $result = null;
        $error = null;

        if ($request->method() === 'POST') {
            if (!Csrf::validate($request->post('_csrf_token'))) {
                $error = 'Invalid session token.';
            } else {
                try {
                    $result = $this->firewall->run(
                        $_POST,
                        (int) $_SESSION['user']['id'],
                        $request->ip()
                    );
                } catch (\Throwable $exception) {
                    $error = $exception->getMessage();
                }
            }
        }

        Response::view('firewall/index', ($this->viewData)([
            'status' => $this->firewall->status(),
            'rules' => $this->firewall->rules(),
            'result' => $result,
            'error' => $error,
        ]));
    }
}
