<?php

namespace CaddyPanel\Auth;

use CaddyPanel\Core\Csrf;
use CaddyPanel\Core\Request;
use CaddyPanel\Core\Response;

class AuthController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function login(): void
    {
        if ($this->auth->check()) {
            Response::redirect('/dashboard');
        }

        $request = new Request();

        if ($request->method() === 'POST') {
            $this->handleLogin($request);
        }

        Response::view('auth/login', ['error' => null]);
    }

    public function logout(): void
    {
        $request = new Request();
        $this->auth->logout($request->ip());
        Response::redirect('/login');
    }

    public function check(): never
    {
        if ($this->auth->check()) {
            http_response_code(204);
            exit;
        }

        http_response_code(401);
        exit;
    }

    private function handleLogin(Request $request): void
    {
        if (!Csrf::validate($request->post('_csrf_token'))) {
            Response::view('auth/login', ['error' => 'Invalid session token.']);
        }

        $username = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');

        if ($this->auth->attempt($username, $password, $request->ip())) {
            Response::redirect('/dashboard');
        }

        Response::view('auth/login', ['error' => 'Invalid username or password.']);
    }
}
