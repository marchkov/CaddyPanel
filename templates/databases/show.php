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
                <h1 style="margin: 0;"><?php echo htmlspecialchars($database['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="muted">Status: <?php echo htmlspecialchars($database['status'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a class="button" href="/databases">Back</a>
                <a class="button" href="/databases/<?php echo (int) $database['id']; ?>/delete">Delete</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($password !== null): ?>
            <section class="card" style="margin-bottom: 16px;">
                <h2 style="margin-top: 0;">Password</h2>
                <p class="muted">This action is written to audit log.</p>
                <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars($password, ENT_QUOTES, 'UTF-8'); ?></pre>
            </section>
        <?php endif; ?>

        <section class="card">
            <p><span class="muted">Host:</span> <?php echo htmlspecialchars($database['host'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Database:</span> <?php echo htmlspecialchars($database['name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Username:</span> <?php echo htmlspecialchars($database['username'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Site:</span> <?php echo htmlspecialchars($database['site_domain'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
            <form method="post" action="/databases/<?php echo (int) $database['id']; ?>" style="display: grid; gap: 10px; max-width: 360px;">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="action" value="show_password">
                <div class="field" style="margin: 0;">
                    <label for="current_password">Panel password</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        <?php echo !empty($revealPasswordRequired) ? 'autofocus' : ''; ?>
                    >
                </div>
                <button class="button" type="submit">Show database password</button>
            </form>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Site Link</h2>
            <form method="post" action="/databases/<?php echo (int) $database['id']; ?>" style="display: grid; gap: 12px; max-width: 520px;">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="action" value="attach_site">
                <div class="field" style="margin: 0;">
                    <label for="site_id">Attach to site</label>
                    <select id="site_id" name="site_id" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo (int) $site['id']; ?>" <?php echo (int) ($database['site_id'] ?? 0) === (int) $site['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="button primary" type="submit">Save site link</button>
                    <?php if (!empty($database['site_id'])): ?>
                        <button
                            class="button"
                            type="submit"
                            name="action"
                            value="detach_site"
                            onclick="return confirm('Detach this database from the site?');"
                        >Detach</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = $database['name'] . ' - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
