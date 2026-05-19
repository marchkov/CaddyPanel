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
                <h1 style="margin: 0;">Users</h1>
                <div class="muted">Admin-only user management.</div>
            </div>
            <a class="button primary" href="/users/create">New user</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Username</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Role</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Reset password</th>
                        <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--border);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $managedUser): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($managedUser['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($managedUser['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?php echo (int) $managedUser['is_active'] === 1 ? 'active' : 'inactive'; ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <form method="post" action="/users/<?php echo (int) $managedUser['id']; ?>/action" style="display: flex; gap: 8px;">
                                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input name="password" type="password" placeholder="New password" style="min-width: 180px;">
                                    <button class="button" type="submit">Reset</button>
                                </form>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <form method="post" action="/users/<?php echo (int) $managedUser['id']; ?>/action" style="display: inline;">
                                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                    <?php if ((int) $managedUser['is_active'] === 1): ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <button class="button" type="submit">Deactivate</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button class="button" type="submit">Activate</button>
                                    <?php endif; ?>
                                </form>
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
$title = 'Users - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
