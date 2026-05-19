<?php

namespace CaddyPanel\Databases;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class DatabaseController
{
    public function __construct(
        private DatabaseService $databases,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        Response::view('databases/index', ($this->viewData)([
            'databases' => $this->databases->all(),
        ]));
    }

    public function create(): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if ($request->method() === 'POST') {
            $this->handleCreate($request);
        }

        Response::view('databases/create', ($this->viewData)([
            'error' => null,
            'old' => [],
        ]));
    }

    public function show(string $id): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();
        $database = $this->databases->find((int) $id);

        if (!$database || $database['deleted_at'] !== null) {
            Response::notFound();
        }

        $password = null;

        if (
            isset($_SESSION['created_database_password'])
            && (int) $_SESSION['created_database_password']['id'] === (int) $id
        ) {
            $password = (string) $_SESSION['created_database_password']['password'];
            unset($_SESSION['created_database_password']);
        }

        if ($request->method() === 'POST') {
            if (!Csrf::validate($request->post('_csrf_token'))) {
                Response::view('databases/show', ($this->viewData)([
                    'database' => $database,
                    'password' => null,
                    'error' => 'Invalid session token.',
                ]));
            }

            if ($request->post('action') === 'show_password') {
                $password = $this->databases->revealPassword((int) $id, (int) $_SESSION['user']['id'], $request->ip());
            }
        }

        Response::view('databases/show', ($this->viewData)([
            'database' => $database,
            'password' => $password,
            'error' => null,
        ]));
    }

    public function delete(string $id): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();
        $database = $this->databases->find((int) $id);

        if (!$database || $database['deleted_at'] !== null) {
            Response::notFound();
        }

        if ($request->method() === 'POST') {
            if (!Csrf::validate($request->post('_csrf_token'))) {
                Response::view('databases/delete', ($this->viewData)([
                    'database' => $database,
                    'error' => 'Invalid session token.',
                ]));
            }

            try {
                $this->databases->delete((int) $id, (int) $_SESSION['user']['id'], $request->ip());
                Response::redirect('/databases');
            } catch (\Throwable $exception) {
                Response::view('databases/delete', ($this->viewData)([
                    'database' => $database,
                    'error' => $exception->getMessage(),
                ]));
            }
        }

        Response::view('databases/delete', ($this->viewData)([
            'database' => $database,
            'error' => null,
        ]));
    }

    private function handleCreate(Request $request): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('databases/create', ($this->viewData)([
                'error' => 'Invalid session token.',
                'old' => $_POST,
            ]));
        }

        try {
            $result = $this->databases->create($_POST, (int) $_SESSION['user']['id'], $request->ip());
            $_SESSION['created_database_password'] = [
                'id' => $result['id'],
                'password' => $result['password'],
            ];
            Response::redirect('/databases/' . $result['id']);
        } catch (\Throwable $exception) {
            Response::view('databases/create', ($this->viewData)([
                'error' => $exception->getMessage(),
                'old' => $_POST,
            ]));
        }
    }
}
