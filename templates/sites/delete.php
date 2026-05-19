<?php ob_start(); ?>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">CaddyPanel</div>
        <nav class="nav">
            <a href="/dashboard">Dashboard</a>
            <?php foreach ($navigation as $item): ?>
                <?php if (!($item['admin_only'] ?? false) || (($user['role'] ?? null) === 'admin')): ?>
                    <a href="<?php echo htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1 style="margin: 0;">Delete site: <?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="muted">Confirmation is required for destructive actions.</div>
            </div>
            <a class="button" href="/sites/<?php echo (int) $site['id']; ?>">Cancel</a>
        </div>

        <section class="card" style="max-width: 680px;">
            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/sites/<?php echo (int) $site['id']; ?>/delete">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>

                <p class="muted">
                    Choose what should be removed. Disabling the host keeps the site record and domain reserved.
                    To create the same domain again later, select Delete files or Delete database so the site is marked deleted.
                </p>

                <div class="field">
                    <label>
                        <input type="checkbox" name="disable_host" value="1" checked style="width: auto;">
                        Disable host only
                    </label>
                </div>

                <div class="field">
                    <label>
                        <input type="checkbox" name="delete_files" value="1" style="width: auto;">
                        Delete files and release site record
                    </label>
                </div>

                <div class="field">
                    <label>
                        <input type="checkbox" name="delete_database" value="1" style="width: auto;">
                        Delete database and release site record
                    </label>
                </div>

                <button class="button primary" type="submit">Confirm action</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Delete Site - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
