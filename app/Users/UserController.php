<?php

namespace CaddyPanel\Users;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class UserController
{
    public function __construct(
        private UserService $users,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('users', ($this->viewData)());
        $this->guard->requireAdmin();

        Response::view('users/index', ($this->viewData)([
            'users' => $this->users->all(),
            'error' => null,
        ]));
    }

    public function create(): void
    {
        $this->guard->requireModule('users', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();

        if ($request->method() === 'POST') {
            $this->handleCreate($request);
        }

        Response::view('users/create', ($this->viewData)([
            'error' => null,
            'old' => [],
        ]));
    }

    public function action(string $id): void
    {
        $this->guard->requireModule('users', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('users/index', ($this->viewData)([
                'users' => $this->users->all(),
                'error' => 'Invalid session token.',
            ]));
        }

        try {
            $action = (string) $request->post('action', '');

            if ($action === 'activate') {
                $this->users->setActive((int) $id, true, (int) $_SESSION['user']['id'], $request->ip());
            } elseif ($action === 'deactivate') {
                $this->users->setActive((int) $id, false, (int) $_SESSION['user']['id'], $request->ip());
            } elseif ($action === 'reset_password') {
                $this->users->resetPassword((int) $id, (string) $request->post('password', ''), (int) $_SESSION['user']['id'], $request->ip());
            } else {
                throw new \InvalidArgumentException('Unknown user action.');
            }

            Response::redirect('/users');
        } catch (\Throwable $exception) {
            Response::view('users/index', ($this->viewData)([
                'users' => $this->users->all(),
                'error' => $exception->getMessage(),
            ]));
        }
    }

    private function handleCreate(Request $request): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('users/create', ($this->viewData)([
                'error' => 'Invalid session token.',
                'old' => $_POST,
            ]));
        }

        try {
            $this->users->create($_POST, (int) $_SESSION['user']['id'], $request->ip());
            Response::redirect('/users');
        } catch (\Throwable $exception) {
            Response::view('users/create', ($this->viewData)([
                'error' => $exception->getMessage(),
                'old' => $_POST,
            ]));
        }
    }
}
