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
                <h1 style="margin: 0;">Admin Tasks</h1>
                <div class="muted">Safe server actions and diagnostics. No arbitrary shell commands.</div>
            </div>
            <form method="post" action="/logout">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <button class="button" type="submit">Logout</button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <section class="card" style="margin-bottom: 16px;">
                <h2 style="margin-top: 0;">Result</h2>
                <p class="muted">Exit code: <?php echo (int) ($result['exit_code'] ?? 1); ?></p>
                <pre style="white-space: pre-wrap; overflow: auto; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars((string) ($result['output'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>
            </section>
        <?php endif; ?>

        <section class="grid">
            <div class="card">
                <h2 style="margin-top: 0;">Actions</h2>
                <form method="post" action="/admin-tasks" style="display: grid; gap: 12px;">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <input type="hidden" name="mode" value="action">
                    <div class="field" style="margin: 0;">
                        <label for="action">Task</label>
                        <select id="action" name="action" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                            <?php foreach ($actions as $action => $label): ?>
                                <option value="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="margin: 0;">
                        <label for="action_service">PHP-FPM service, optional</label>
                        <input id="action_service" name="service" placeholder="php8.3-fpm">
                    </div>
                    <button class="button primary" type="submit">Run task</button>
                </form>
            </div>

            <div class="card">
                <h2 style="margin-top: 0;">Logs</h2>
                <form method="post" action="/admin-tasks" style="display: grid; gap: 12px;">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <input type="hidden" name="mode" value="logs">
                    <div class="field" style="margin: 0;">
                        <label for="target">Log</label>
                        <select id="target" name="target" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                            <?php foreach ($logTargets as $target => $label): ?>
                                <option value="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="margin: 0;">
                        <label for="log_service">PHP-FPM service, optional</label>
                        <input id="log_service" name="service" placeholder="php8.3-fpm">
                    </div>
                    <div class="field" style="margin: 0;">
                        <label for="lines">Lines</label>
                        <input id="lines" name="lines" value="120">
                    </div>
                    <button class="button" type="submit">Read log</button>
                </form>
            </div>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Admin Tasks - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
