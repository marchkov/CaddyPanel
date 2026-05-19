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
                <h1 style="margin: 0;">Delete database: <?php echo htmlspecialchars($database['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="muted">This requires confirmation.</div>
            </div>
            <a class="button" href="/databases/<?php echo (int) $database['id']; ?>">Cancel</a>
        </div>

        <section class="card" style="max-width: 680px;">
            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <p class="muted">This will drop the MariaDB database and user in production mode. In local mode, provisioning is skipped.</p>
            <form method="post" action="/databases/<?php echo (int) $database['id']; ?>/delete">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <button class="button primary" type="submit">Confirm delete</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Delete Database - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
