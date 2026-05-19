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
                <h1 style="margin: 0;">Updates</h1>
                <div class="muted">Check and apply Git updates from the configured repository.</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="card" style="max-width: 760px; margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Configuration</h2>
            <form method="post" action="/updates">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="action" value="save_config">
                <div class="field">
                    <label for="branch">Branch</label>
                    <input id="branch" name="branch" value="<?php echo htmlspecialchars($config['branch'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="auto_check" value="1" style="width: auto;" <?php echo $config['auto_check'] ? 'checked' : ''; ?>>
                        Enable automatic update checks
                    </label>
                </div>
                <button class="button" type="submit">Save configuration</button>
            </form>
        </section>

        <section class="card" style="max-width: 760px; margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Manual Actions</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <form method="post" action="/updates">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <input type="hidden" name="action" value="check">
                    <button class="button" type="submit">Check updates</button>
                </form>
                <form method="post" action="/updates">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <input type="hidden" name="action" value="apply">
                    <button class="button primary" type="submit">Apply update</button>
                </form>
            </div>
            <p class="muted">Apply uses fast-forward only and refuses dirty worktrees.</p>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Last Check</h2>
            <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
        </section>

        <?php if ($result !== null): ?>
            <section class="card" style="margin-top: 16px;">
                <h2 style="margin-top: 0;">Action Result</h2>
                <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Updates - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
