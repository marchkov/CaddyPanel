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
                <h1 style="margin: 0;">Dashboard</h1>
                <div class="muted">Signed in as <?php echo htmlspecialchars($user['username'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <form method="post" action="/logout">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <button class="button" type="submit">Logout</button>
            </form>
        </div>

        <section class="grid">
            <div class="card">
                <div class="muted">Sites</div>
                <div class="metric"><?php echo (int) $stats['sites']; ?></div>
            </div>
            <div class="card">
                <div class="muted">Databases</div>
                <div class="metric"><?php echo (int) $stats['databases']; ?></div>
            </div>
            <div class="card">
                <div class="muted">Backups</div>
                <div class="metric"><?php echo (int) $stats['backups']; ?></div>
            </div>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">System Status</h2>
            <div class="grid">
                <div>
                    <div class="muted">Caddy</div>
                    <div class="metric"><?php echo htmlspecialchars($systemStatus['caddy'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div>
                    <div class="muted">PHP-FPM</div>
                    <div class="metric"><?php echo htmlspecialchars($systemStatus['php_fpm'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div>
                    <div class="muted">MariaDB</div>
                    <div class="metric"><?php echo htmlspecialchars($systemStatus['mariadb'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <p class="muted">Disk: <?php echo htmlspecialchars($systemStatus['disk'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($systemStatus['error'])): ?>
                <p class="muted"><?php echo htmlspecialchars($systemStatus['error'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Dashboard - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
