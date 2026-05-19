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
                <h1 style="margin: 0;">Databases</h1>
                <div class="muted">MariaDB database records and provisioning.</div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a class="button primary" href="/databases/create">New database</a>
                <form method="post" action="/logout">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <button class="button" type="submit">Logout</button>
                </form>
            </div>
        </div>

        <?php if (empty($databases)): ?>
            <section class="card"><p class="muted" style="margin: 0;">No databases yet.</p></section>
        <?php else: ?>
            <section class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Name</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">User</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Site</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                            <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--border);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($databases as $database): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                    <a href="/databases/<?php echo (int) $database['id']; ?>"><?php echo htmlspecialchars($database['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($database['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($database['site_domain'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($database['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                    <a class="button" href="/databases/<?php echo (int) $database['id']; ?>">View</a>
                                    <a class="button" href="/databases/<?php echo (int) $database['id']; ?>/delete">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Databases - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
