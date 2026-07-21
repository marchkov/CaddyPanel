#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use CaddyPanel\Core\Database;

$jobId = null;

for ($i = 1; $i < $argc; $i += 2) {
    if (($argv[$i] ?? '') === 'job_id') {
        $jobId = (int) ($argv[$i + 1] ?? 0);
    }
}

if ($jobId === null || $jobId < 1) {
    fwrite(STDERR, "Usage: php-fpm-job-worker.php job_id <id>\n");
    exit(2);
}

$config = require dirname(__DIR__) . '/config/app.php';
$database = new Database($config['database']['path']);
$now = date('Y-m-d H:i:s');
$job = $database->fetch('SELECT * FROM php_version_jobs WHERE id = ?', [$jobId]);

if (!$job) {
    fwrite(STDERR, "PHP version job not found: {$jobId}\n");
    exit(1);
}

if (!in_array($job['status'], ['queued', 'running'], true)) {
    exit(0);
}

$action = (string) $job['action'];
$version = (string) $job['version'];

if (!in_array($action, ['install', 'uninstall'], true) || preg_match('/^\d+\.\d+$/', $version) !== 1) {
    finishJob($database, $jobId, 'failed', 2, 'Invalid PHP version job payload.');
    exit(2);
}

$database->execute(
    'UPDATE php_version_jobs SET status = ?, started_at = COALESCE(started_at, ?), updated_at = ? WHERE id = ?',
    ['running', $now, $now, $jobId]
);

$script = dirname(__DIR__) . '/bin/php-fpm-' . $action;
$result = runCommand([$script, 'version', $version]);
$status = $result['exit_code'] === 0 ? 'succeeded' : 'failed';

if ($status === 'succeeded') {
    $database->execute("DELETE FROM settings WHERE key = 'php_versions_system_cache'");
}

finishJob($database, $jobId, $status, $result['exit_code'], $result['output']);
auditJob($database, $job, $status, $result['output']);

exit($result['exit_code']);

function runCommand(array $command): array
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes);

    if (!is_resource($process)) {
        return [
            'exit_code' => 1,
            'output' => 'Unable to start command.',
        ];
    }

    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit_code' => proc_close($process),
        'output' => trim($output),
    ];
}

function finishJob(Database $database, int $jobId, string $status, int $exitCode, string $output): void
{
    $now = date('Y-m-d H:i:s');
    $database->execute(
        'UPDATE php_version_jobs
         SET status = ?, exit_code = ?, output = ?, finished_at = ?, updated_at = ?
         WHERE id = ?',
        [$status, $exitCode, $output, $now, $now, $jobId]
    );
}

function auditJob(Database $database, array $job, string $status, string $output): void
{
    $action = (string) $job['action'];
    $version = (string) $job['version'];
    $auditStatus = $status === 'succeeded' ? 'success' : 'failed';
    $message = ucfirst($action) . ' PHP ' . $version . ' job #' . $job['id'] . ' ' . $status . '.';

    if ($output !== '') {
        $message .= ' ' . $output;
    }

    $database->execute(
        'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
        [
            $job['created_by_user_id'] !== null ? (int) $job['created_by_user_id'] : null,
            'php_versions_' . $action,
            'php_versions',
            $auditStatus,
            $message,
            $job['ip_address'] ?? null,
            date('Y-m-d H:i:s'),
        ]
    );
}
