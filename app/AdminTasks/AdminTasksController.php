<?php

namespace CaddyPanel\AdminTasks;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class AdminTasksController
{
    public function __construct(
        private AdminTasksService $tasks,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function index(): void
    {
        $this->guard->requireModule('admin_tasks', ($this->viewData)());
        $this->guard->requireAdmin();

        $request = new Request();
        $result = null;
        $error = null;

        if ($request->method() === 'POST') {
            if (!Csrf::validate($request->post('_csrf_token'))) {
                $error = 'Invalid session token.';
            } else {
                try {
                    $mode = (string) $request->post('mode', '');

                    if ($mode === 'service') {
                        $result = $this->tasks->controlService(
                            (string) $request->post('service', ''),
                            (string) $request->post('operation', ''),
                            (int) $_SESSION['user']['id'],
                            $request->ip()
                        );
                    } elseif ($mode === 'action') {
                        $result = $this->tasks->runAction(
                            (string) $request->post('action', ''),
                            (string) $request->post('service', ''),
                            (int) $_SESSION['user']['id'],
                            $request->ip()
                        );
                    } elseif ($mode === 'logs') {
                        $result = $this->tasks->readLog(
                            (string) $request->post('target', ''),
                            (int) $request->post('lines', 120),
                            (string) $request->post('service', ''),
                            (int) $_SESSION['user']['id'],
                            $request->ip()
                        );
                    } else {
                        throw new \InvalidArgumentException('Unknown admin task mode.');
                    }
                } catch (\Throwable $exception) {
                    $error = $exception->getMessage();
                }
            }
        }

        Response::view('admin-tasks/index', ($this->viewData)([
            'services' => $this->tasks->services(),
            'actions' => $this->tasks->actions(),
            'logTargets' => $this->tasks->logTargets(),
            'result' => $result,
            'error' => $error,
        ]));
    }
}
