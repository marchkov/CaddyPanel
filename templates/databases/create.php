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
                <h1 style="margin: 0;">Create Database</h1>
                <div class="muted">Name must match ^[a-z][a-z0-9_]{0,11}$.</div>
            </div>
            <a class="button" href="/databases">Back</a>
        </div>

        <section class="card" style="max-width: 680px;">
            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/databases/create">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <div class="field">
                    <label for="name">Database name</label>
                    <input id="name" name="name" placeholder="example_a1f" value="<?php echo htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label for="domain_hint">Domain hint for generated name</label>
                    <input id="domain_hint" name="domain_hint" placeholder="example.com" value="<?php echo htmlspecialchars($old['domain_hint'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <option value="">No site</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo (int) $site['id']; ?>" <?php echo (string) ($old['site_id'] ?? '') === (string) $site['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button primary" type="submit">Create database</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Create Database - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
