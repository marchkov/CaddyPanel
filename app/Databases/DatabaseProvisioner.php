<?php

namespace CaddyPanel\Databases;

use CaddyPanel\System\CommandRunner;

class DatabaseProvisioner
{
    public function __construct(private CommandRunner $commands)
    {
    }

    public function create(string $name, string $username, string $password): string
    {
        $result = $this->commands->run('db-create', [
            'name' => $name,
            'username' => $username,
            'password' => $password,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to create database: ' . $result['output']);
        }

        return $result['output'];
    }

    public function delete(string $name, string $username): string
    {
        $result = $this->commands->run('db-delete', [
            'name' => $name,
            'username' => $username,
        ]);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to delete database: ' . $result['output']);
        }

        return $result['output'];
    }
}
