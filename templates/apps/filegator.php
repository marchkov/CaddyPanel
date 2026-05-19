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
                <h1 style="margin: 0;">FileGator</h1>
                <div class="muted">File manager protected by CaddyPanel login.</div>
            </div>
        </div>

        <section class="card">
            <p><span class="muted">Allowed root:</span> <?php echo htmlspecialchars($app['root'], ENT_QUOTES, 'UTF-8'); ?></p>

            <?php if ($app['installed']): ?>
                <p>FileGator is installed.</p>
                <p class="muted">Entry: <?php echo htmlspecialchars($app['entry'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="muted">The route is protected by CaddyPanel authentication. Runtime handoff is handled after VPS installer verification.</p>
            <?php else: ?>
                <h2>Not installed</h2>
                <p class="muted">Expected file:</p>
                <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars($app['entry'], ENT_QUOTES, 'UTF-8'); ?></pre>
                <p class="muted">The installer installs FileGator to this path.</p>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'FileGator - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
