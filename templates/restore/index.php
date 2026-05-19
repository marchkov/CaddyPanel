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
                <h1 style="margin: 0;">Restore</h1>
                <div class="muted">Select a successful backup to inspect and stage restore.</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Started</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Site</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">File</th>
                        <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--border);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['started_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($backup['backup_file'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <?php if ($backup['status'] === 'success'): ?>
                                    <a class="button" href="/restore/<?php echo (int) $backup['id']; ?>">Inspect</a>
                                <?php else: ?>
                                    <span class="muted">Unavailable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Restore - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
