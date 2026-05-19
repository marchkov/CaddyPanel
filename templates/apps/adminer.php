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
                <h1 style="margin: 0;">Adminer</h1>
                <div class="muted">Database UI protected by CaddyPanel login.</div>
            </div>
        </div>

        <section class="card">
            <?php if ($app['installed']): ?>
                <p>Adminer is installed.</p>
                <p class="muted">Entry: <?php echo htmlspecialchars($app['entry'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="muted">Open `/db` to launch Adminer through CaddyPanel authentication.</p>
            <?php else: ?>
                <h2 style="margin-top: 0;">Not installed</h2>
                <p class="muted">Expected file:</p>
                <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars($app['entry'], ENT_QUOTES, 'UTF-8'); ?></pre>
                <p class="muted">The installer downloads Adminer to this path.</p>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Adminer - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
