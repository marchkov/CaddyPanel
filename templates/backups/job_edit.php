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
                <h1 style="margin: 0;">Edit scheduled backup</h1>
                <div class="muted"><?php echo htmlspecialchars($job['domain'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a class="button" href="/backups">Back</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="card" style="max-width: 720px;">
            <form method="post" action="/backups/jobs/<?php echo (int) $job['id']; ?>/edit">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <div class="field">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo (int) $site['id']; ?>" <?php echo (int) $site['id'] === (int) $job['site_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="schedule_type">Schedule</label>
                    <select id="schedule_type" name="schedule_type" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php foreach (['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($job['schedule_type'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="schedule_time">Time</label>
                    <input id="schedule_time" name="schedule_time" value="<?php echo htmlspecialchars($job['schedule_time'] ?? '03:00', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label><input type="checkbox" name="enabled" value="1" <?php echo (int) ($job['enabled'] ?? 0) === 1 ? 'checked' : ''; ?> style="width: auto;"> Enabled</label>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="include_files" value="1" <?php echo (int) ($job['include_files'] ?? 0) === 1 ? 'checked' : ''; ?> style="width: auto;"> Include files</label>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="include_database" value="1" <?php echo (int) ($job['include_database'] ?? 0) === 1 ? 'checked' : ''; ?> style="width: auto;"> Include database</label>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="include_caddy_config" value="1" <?php echo (int) ($job['include_caddy_config'] ?? 0) === 1 ? 'checked' : ''; ?> style="width: auto;"> Include Caddy config</label>
                </div>
                <button class="button primary" type="submit">Save schedule</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Edit Scheduled Backup - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
