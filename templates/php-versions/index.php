<?php
$installed = $overview['installed'] ?? [];
$available = $overview['available'] ?? [];
$configuredMissing = $overview['configured_missing'] ?? [];
$jobs = $overview['jobs'] ?? [];
$isActiveJob = static fn (?array $job): bool => $job !== null && in_array($job['status'] ?? '', ['queued', 'running'], true);
$jobButtonLabel = static function (?array $job): string {
    if (!$job) {
        return '';
    }

    return ($job['action'] ?? '') === 'uninstall' ? 'Removing...' : 'Installing...';
};

ob_start();
?>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">CaddyPanel</div>
        <nav class="nav">
            <a href="/dashboard">Dashboard</a>
            <?php foreach ($navigation as $item): ?>
                <?php if (!($item['admin_only'] ?? false) || (($user['role'] ?? null) === 'admin')): ?>
                    <a href="<?php echo htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1 style="margin: 0;">PHP Versions</h1>
                <div class="muted">Detected PHP-FPM runtimes and APT availability. System checks are cached for up to 7 days.</div>
            </div>
            <form method="post" action="/php-versions">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="action" value="refresh">
                <button class="button primary" type="submit">Refresh now</button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($configuredMissing !== []): ?>
            <section class="card" style="margin-bottom: 16px; border-color: var(--danger);">
                <h2 style="margin-top: 0;">Configured but missing</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">Version</th>
                            <th style="text-align: left; padding: 10px;">Socket</th>
                            <th style="text-align: left; padding: 10px;">Sites</th>
                            <th style="text-align: left; padding: 10px;">Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configuredMissing as $version): ?>
                            <tr>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['fpm_socket'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo (int) ($version['site_count'] ?? 0); ?></td>
                                <td style="padding: 10px;">
                                    <?php if ((int) ($version['is_default'] ?? 0) === 1): ?><span class="badge">Default</span><?php endif; ?>
                                    <?php if ((int) ($version['is_panel_runtime'] ?? 0) === 1): ?><span class="badge">Panel</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2 style="margin-top: 0;">Installed PHP-FPM</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 10px;">Version</th>
                        <th style="text-align: left; padding: 10px;">Socket</th>
                        <th style="text-align: left; padding: 10px;">Status</th>
                        <th style="text-align: left; padding: 10px;">Sites</th>
                        <th style="text-align: left; padding: 10px;">Manual</th>
                        <th style="text-align: left; padding: 10px;">Detected</th>
                        <th style="text-align: left; padding: 10px;">Default</th>
                        <th style="text-align: left; padding: 10px;">Panel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installed as $version): ?>
                        <tr>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['fpm_socket'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;">
                                <?php if ($isActiveJob($version['job'] ?? null)): ?>
                                    <span class="badge" data-job-version="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>" data-job-active="1"><?php echo htmlspecialchars($jobButtonLabel($version['job']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($version['runtime_status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;"><?php echo (int) ($version['site_count'] ?? 0); ?></td>
                            <td style="padding: 10px;">
                                <?php if ($isActiveJob($version['job'] ?? null)): ?>
                                    <span class="muted">Busy</span>
                                <?php elseif (!empty($version['manual'])): ?>
                                    <span class="badge">Manual</span>
                                <?php else: ?>
                                    <form method="post" action="/php-versions">
                                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                        <input type="hidden" name="action" value="mark_manual">
                                        <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="button" type="submit">Mark manual</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['detected_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;">
                                <?php if ((int) ($version['is_default'] ?? 0) === 1): ?>
                                    <span class="badge">Default</span>
                                <?php elseif ($isActiveJob($version['job'] ?? null)): ?>
                                    <span class="muted">Busy</span>
                                <?php else: ?>
                                    <form method="post" action="/php-versions">
                                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="button" type="submit">Set default</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ((int) ($version['is_panel_runtime'] ?? 0) === 1): ?>
                                    <span class="badge">Panel</span>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Available PHP-FPM</h2>
            <?php if ($available === []): ?>
                <p class="muted" style="margin-bottom: 0;">No PHP-FPM versions were found in the configured APT repositories.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">Version</th>
                            <th style="text-align: left; padding: 10px;">Package</th>
                            <th style="text-align: left; padding: 10px;">Candidate</th>
                            <th style="text-align: left; padding: 10px;">Installed</th>
                            <th style="text-align: left; padding: 10px;">Sites</th>
                            <th style="text-align: right; padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available as $version): ?>
                            <?php $job = $version['job'] ?? null; ?>
                            <tr>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['package'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['candidate'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;">
                                    <?php if (!empty($version['installed'])): ?>
                                        <span class="badge">Installed</span>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px;"><?php echo (int) ($version['site_count'] ?? 0); ?></td>
                                <td style="padding: 10px; text-align: right;">
                                    <?php if ($isActiveJob($job)): ?>
                                        <button class="button" type="button" disabled data-job-version="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>" data-job-active="1"><?php echo htmlspecialchars($jobButtonLabel($job), ENT_QUOTES, 'UTF-8'); ?></button>
                                    <?php elseif (!empty($version['installed'])): ?>
                                        <?php if (!empty($version['can_uninstall'])): ?>
                                            <form method="post" action="/php-versions" style="display: inline-block;">
                                                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                                <input type="hidden" name="action" value="uninstall">
                                                <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="button" type="submit">Uninstall</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">
                                                <?php
                                                $reasons = [];

                                                if (!empty($version['is_panel_runtime'])) {
                                                    $reasons[] = 'panel';
                                                }

                                                if (!empty($version['is_default'])) {
                                                    $reasons[] = 'default';
                                                }

                                                if ((int) ($version['site_count'] ?? 0) > 0) {
                                                    $reasons[] = 'sites';
                                                }

                                                echo htmlspecialchars('In use: ' . implode(', ', $reasons), ENT_QUOTES, 'UTF-8');
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="post" action="/php-versions" style="display: inline-block;">
                                            <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                            <input type="hidden" name="action" value="install">
                                            <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="button primary" type="submit">Install</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($job['status'] ?? '') === 'failed' && !empty($job['output'])): ?>
                                        <details style="margin-top: 8px; text-align: left;">
                                            <summary class="muted">Error details</summary>
                                            <pre style="white-space: pre-wrap; max-height: 220px; overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars($job['output'], ENT_QUOTES, 'UTF-8'); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($jobs !== []): ?>
            <section class="card" style="margin-top: 16px;">
                <h2 style="margin-top: 0;">Recent PHP Jobs</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">ID</th>
                            <th style="text-align: left; padding: 10px;">Action</th>
                            <th style="text-align: left; padding: 10px;">Version</th>
                            <th style="text-align: left; padding: 10px;">Status</th>
                            <th style="text-align: left; padding: 10px;">Finished</th>
                            <th style="text-align: left; padding: 10px;">Output</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($jobs, 0, 10) as $job): ?>
                            <tr>
                                <td style="padding: 10px;"><?php echo (int) $job['id']; ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($job['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($job['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($job['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($job['finished_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;">
                                    <?php if (!empty($job['output'])): ?>
                                        <details>
                                            <summary class="muted">View log</summary>
                                            <pre style="white-space: pre-wrap; max-height: 220px; overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars($job['output'], ENT_QUOTES, 'UTF-8'); ?></pre>
                                        </details>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </main>
</div>
<script>
(() => {
    const activeElements = Array.from(document.querySelectorAll('[data-job-active="1"]'));

    if (activeElements.length === 0) {
        return;
    }

    const labels = {
        install: 'Installing...',
        uninstall: 'Removing...'
    };

    const timer = window.setInterval(async () => {
        try {
            const response = await fetch('/php-versions/jobs', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const jobs = Array.isArray(payload.jobs) ? payload.jobs : [];
            const activeJobs = jobs.filter((job) => job.status === 'queued' || job.status === 'running');

            for (const element of activeElements) {
                const version = element.dataset.jobVersion;
                const job = activeJobs.find((item) => item.version === version);

                if (job) {
                    element.textContent = labels[job.action] || 'Working...';
                }
            }

            if (activeJobs.length === 0) {
                window.clearInterval(timer);
                window.location.reload();
            }
        } catch (error) {
        }
    }, 2000);
})();
</script>
<?php
$content = ob_get_clean();
$title = 'PHP Versions - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
