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
                <h1 style="margin: 0;">PHP Versions</h1>
                <div class="muted">Detected PHP-FPM sockets. CaddyPanel does not install PHP versions from the UI.</div>
            </div>
            <form method="post" action="/php-versions">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="action" value="refresh">
                <button class="button primary" type="submit">Detect sockets</button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 10px;">Version</th>
                        <th style="text-align: left; padding: 10px;">Socket</th>
                        <th style="text-align: left; padding: 10px;">Detected</th>
                        <th style="text-align: left; padding: 10px;">Default</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versions as $version): ?>
                        <tr>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['fpm_socket'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['detected_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;">
                                <?php if ((int) ($version['is_default'] ?? 0) === 1): ?>
                                    <span class="badge">Default</span>
                                <?php else: ?>
                                    <form method="post" action="/php-versions">
                                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="button" type="submit">Set default</button>
                                    </form>
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
$title = 'PHP Versions - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
