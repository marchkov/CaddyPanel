<?php ob_start(); ?>
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
                <h1 style="margin: 0;">Backups</h1>
                <div class="muted">Queued manual runs and completed backup archives.</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="card" style="max-width: 720px; margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Queue manual backup</h2>
            <?php if (empty($sites)): ?>
                <p class="muted">No sites available.</p>
            <?php else: ?>
                <form method="post" action="/backups/create">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <div class="field">
                        <label for="site_id">Site</label>
                        <select id="site_id" name="site_id" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo (int) $site['id']; ?>"><?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="button primary" type="submit">Queue backup</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" style="max-width: 720px; margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Create scheduled backup</h2>
            <?php if (empty($sites)): ?>
                <p class="muted">No sites available.</p>
            <?php else: ?>
                <form method="post" action="/backups/jobs/create">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <div class="field">
                        <label for="job_site_id">Site</label>
                        <select id="job_site_id" name="site_id" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo (int) $site['id']; ?>"><?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="schedule_type">Schedule</label>
                        <select id="schedule_type" name="schedule_type" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                            <option value="hourly">Hourly</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="schedule_time">Time</label>
                        <input id="schedule_time" name="schedule_time" value="03:00">
                    </div>
                    <div class="field">
                        <label for="retention_days">Retention days</label>
                        <input id="retention_days" name="retention_days" value="14">
                    </div>
                    <div class="field">
                        <label><input type="checkbox" name="enabled" value="1" checked style="width: auto;"> Enabled</label>
                    </div>
                    <div class="field">
                        <label><input type="checkbox" name="include_files" value="1" checked style="width: auto;"> Include files</label>
                    </div>
                    <div class="field">
                        <label><input type="checkbox" name="include_database" value="1" checked style="width: auto;"> Include database</label>
                    </div>
                    <div class="field">
                        <label><input type="checkbox" name="include_caddy_config" value="1" checked style="width: auto;"> Include Caddy config</label>
                    </div>
                    <button class="button" type="submit">Create schedule</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" style="padding: 0; overflow: hidden; margin-bottom: 16px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Site</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Schedule</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Next run</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Last run</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Includes</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($job['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($job['schedule_type'] . ' ' . $job['schedule_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($job['next_run_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($job['last_run_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <?php
                                $includes = [];
                                if ((int) $job['include_files'] === 1) {
                                    $includes[] = 'files';
                                }
                                if ((int) $job['include_database'] === 1) {
                                    $includes[] = 'database';
                                }
                                if ((int) $job['include_caddy_config'] === 1) {
                                    $includes[] = 'caddy';
                                }
                                echo htmlspecialchars(implode(', ', $includes) ?: '-', ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo (int) $job['enabled'] === 1 ? 'enabled' : 'disabled'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Started</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Site</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">File</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Message</th>
                        <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--border);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['started_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['backup_file'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <?php if ($backup['status'] === 'success'): ?>
                                    <a class="button" href="/backups/<?php echo (int) $backup['id']; ?>/download">Download</a>
                                    <a class="button" href="/restore/<?php echo (int) $backup['id']; ?>">Restore</a>
                                    <form method="post" action="/backups/<?php echo (int) $backup['id']; ?>/delete" style="display: inline;" onsubmit="return confirm('Delete this backup?');">
                                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                        <button class="button" type="submit">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Backups - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
