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
                <h1 style="margin: 0;"><?php echo htmlspecialchars($section, ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="muted">This module is planned but not implemented yet.</div>
            </div>
            <form method="post" action="/logout">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <button class="button" type="submit">Logout</button>
            </form>
        </div>

        <section class="card">
            <p class="muted">The foundation is ready. This section will be built in a later milestone.</p>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = $section . ' - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
