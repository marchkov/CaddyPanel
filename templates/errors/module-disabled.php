<?php ob_start(); ?>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">CaddyPanel</div>
        <nav class="nav">
            <a href="/dashboard">Dashboard</a>
            <?php foreach (($navigation ?? []) as $item): ?>
                <?php if (!($item['admin_only'] ?? false) || (($user['role'] ?? null) === 'admin')): ?>
                    <a href="<?php echo htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main">
        <section class="card">
            <h1 style="margin-top: 0;">Module disabled</h1>
            <p class="muted">The requested module is currently disabled by administrator.</p>
            <a class="button" href="/dashboard">Back to dashboard</a>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Module disabled - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
