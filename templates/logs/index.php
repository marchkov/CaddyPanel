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
                <h1 style="margin: 0;">Logs</h1>
                <div class="muted">Site logs are available to managers and admins. Audit logs are admin-only.</div>
            </div>
        </div>

        <section class="card" style="padding: 0; overflow: hidden; margin-bottom: 16px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Site</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Access</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($site['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><a class="button" href="/logs/sites/<?php echo (int) $site['id']; ?>?type=access">Open</a></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><a class="button" href="/logs/sites/<?php echo (int) $site['id']; ?>?type=error">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ($showAudit): ?>
            <section class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Time</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">User</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Action</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Target</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                            <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($log['username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                    <?php echo htmlspecialchars(($log['target_type'] ?? '-') . ':' . ($log['target_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($log['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($log['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
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
$title = 'Logs - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
