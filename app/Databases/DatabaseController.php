<?php

namespace CaddyPanel\Databases;

use CaddyPanel\Auth\AuthService;
use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Sites\SiteService;
use CaddyPanel\Support\AuthGuard;

class DatabaseController
{
    public function __construct(
        private DatabaseService $databases,
        private SiteService $sites,
        private AuthService $auth,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $message = $_SESSION['database_action_message'] ?? null;
        $error = $_SESSION['database_action_error'] ?? null;
        unset($_SESSION['database_action_message'], $_SESSION['database_action_error']);

        Response::view('databases/index', ($this->viewData)([
            'databases' => $this->databases->all(),
            'message' => $message,
            'error' => $error,
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
            'sites' => $this->sites->all(),
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
                    'sites' => $this->sites->all(),
                    'password' => null,
                    'error' => 'Invalid session token.',
                    'revealPasswordRequired' => false,
                ]));
            }

            if ($request->post('action') === 'show_password') {
                if (!$this->auth->verifyCurrentUserPassword((string) $request->post('current_password', ''))) {
                    $this->databases->auditPasswordRevealFailure((int) $id, (int) $_SESSION['user']['id'], $request->ip());
                    Response::view('databases/show', ($this->viewData)([
                        'database' => $database,
                        'sites' => $this->sites->all(),
                        'password' => null,
                        'error' => 'Current password confirmation failed.',
                        'revealPasswordRequired' => true,
                    ]));
                }

                $password = $this->databases->revealPassword((int) $id, (int) $_SESSION['user']['id'], $request->ip());
            } elseif ($request->post('action') === 'attach_site') {
                try {
                    $this->databases->attachToSite((int) $id, (int) $request->post('site_id'), (int) $_SESSION['user']['id'], $request->ip());
                    Response::redirect('/databases/' . (int) $id);
                } catch (\Throwable $exception) {
                    Response::view('databases/show', ($this->viewData)([
                        'database' => $database,
                        'sites' => $this->sites->all(),
                        'password' => null,
                        'error' => $exception->getMessage(),
                        'revealPasswordRequired' => false,
                    ]));
                }
            } elseif ($request->post('action') === 'detach_site') {
                try {
                    $this->databases->detachFromSite((int) $id, (int) $_SESSION['user']['id'], $request->ip());
                    Response::redirect('/databases/' . (int) $id);
                } catch (\Throwable $exception) {
                    Response::view('databases/show', ($this->viewData)([
                        'database' => $database,
                        'sites' => $this->sites->all(),
                        'password' => null,
                        'error' => $exception->getMessage(),
                        'revealPasswordRequired' => false,
                    ]));
                }
            }
        }

        Response::view('databases/show', ($this->viewData)([
            'database' => $database,
            'sites' => $this->sites->all(),
            'password' => $password,
            'error' => null,
            'revealPasswordRequired' => false,
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

    public function health(string $id): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            $_SESSION['database_action_error'] = 'Invalid session token.';
            Response::redirect('/databases');
        }

        try {
            $_SESSION['database_action_message'] = $this->databases->health((int) $id, (int) $_SESSION['user']['id'], $request->ip());
        } catch (\Throwable $exception) {
            $_SESSION['database_action_error'] = $exception->getMessage();
        }

        Response::redirect('/databases');
    }

    public function backup(string $id): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            $_SESSION['database_action_error'] = 'Invalid session token.';
            Response::redirect('/databases');
        }

        try {
            $file = $this->databases->backup((int) $id, (int) $_SESSION['user']['id'], $request->ip());
            $this->download($file);
        } catch (\Throwable $exception) {
            $_SESSION['database_action_error'] = $exception->getMessage();
            Response::redirect('/databases');
        }
    }

    public function restore(string $id): void
    {
        $this->guard->requireModule('databases', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $request = new Request();

        if (!Csrf::validate($request->post('_csrf_token'))) {
            $_SESSION['database_action_error'] = 'Invalid session token.';
            Response::redirect('/databases');
        }

        try {
            $_SESSION['database_action_message'] = $this->databases->restore(
                (int) $id,
                $_FILES['restore_file'] ?? [],
                (int) $_SESSION['user']['id'],
                $request->ip()
            );
        } catch (\Throwable $exception) {
            $_SESSION['database_action_error'] = $exception->getMessage();
        }

        Response::redirect('/databases');
    }

    private function handleCreate(Request $request): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('databases/create', ($this->viewData)([
                'error' => 'Invalid session token.',
                'old' => $_POST,
                'sites' => $this->sites->all(),
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
                'sites' => $this->sites->all(),
            ]));
        }
    }

    private function download(string $file): never
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('Backup file is not available.');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
