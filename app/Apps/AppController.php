<?php

namespace CaddyPanel\Apps;

use CaddyPanel\Core\Response;
use CaddyPanel\Support\AuthGuard;

class AppController
{
    public function __construct(
        private AppLocator $apps,
        private AuthGuard $guard,
        private \Closure $viewData
    ) {
    }

    public function adminer(): void
    {
        $this->guard->requireModule('adminer', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        $app = $this->apps->adminer();

        if ($app['installed']) {
            $this->runPhpApp($app['entry'], '/db');
        }

        Response::view('apps/adminer', ($this->viewData)([
            'app' => $app,
        ]));
    }

    public function filegator(): void
    {
        $this->guard->requireModule('filegator', ($this->viewData)());
        $this->guard->requireManagerOrAdmin();

        Response::view('apps/filegator', ($this->viewData)([
            'app' => $this->apps->filegator(),
        ]));
    }

    private function runPhpApp(string $entry, string $scriptName): never
    {
        if (!is_file($entry)) {
            Response::notFound();
        }

        $_SERVER['SCRIPT_FILENAME'] = $entry;
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['PHP_SELF'] = $scriptName;

        chdir(dirname($entry));
        require $entry;
        exit;
    }
}
