<?php
$installed = $overview['installed'] ?? [];
$available = $overview['available'] ?? [];
$configuredMissing = $overview['configured_missing'] ?? [];

ob_start();
?>
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
                <div class="muted">Detected PHP-FPM runtimes and APT availability. System checks are cached for up to 7 days.</div>
            </div>
            <form method="post" action="/php-versions">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="action" value="refresh">
                <button class="button primary" type="submit">Refresh now</button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($configuredMissing !== []): ?>
            <section class="card" style="margin-bottom: 16px; border-color: var(--danger);">
                <h2 style="margin-top: 0;">Configured but missing</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">Version</th>
                            <th style="text-align: left; padding: 10px;">Socket</th>
                            <th style="text-align: left; padding: 10px;">Sites</th>
                            <th style="text-align: left; padding: 10px;">Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configuredMissing as $version): ?>
                            <tr>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['fpm_socket'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo (int) ($version['site_count'] ?? 0); ?></td>
                                <td style="padding: 10px;">
                                    <?php if ((int) ($version['is_default'] ?? 0) === 1): ?><span class="badge">Default</span><?php endif; ?>
                                    <?php if ((int) ($version['is_panel_runtime'] ?? 0) === 1): ?><span class="badge">Panel</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2 style="margin-top: 0;">Installed PHP-FPM</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 10px;">Version</th>
                        <th style="text-align: left; padding: 10px;">Socket</th>
                        <th style="text-align: left; padding: 10px;">Status</th>
                        <th style="text-align: left; padding: 10px;">Sites</th>
                        <th style="text-align: left; padding: 10px;">Manual</th>
                        <th style="text-align: left; padding: 10px;">Detected</th>
                        <th style="text-align: left; padding: 10px;">Default</th>
                        <th style="text-align: left; padding: 10px;">Panel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installed as $version): ?>
                        <tr>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['fpm_socket'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($version['runtime_status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 10px;"><?php echo (int) ($version['site_count'] ?? 0); ?></td>
                            <td style="padding: 10px;">
                                <?php if (!empty($version['manual'])): ?>
                                    <span class="badge">Manual</span>
                                <?php else: ?>
                                    <form method="post" action="/php-versions">
                                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                        <input type="hidden" name="action" value="mark_manual">
                                        <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="button" type="submit">Mark manual</button>
                                    </form>
                                <?php endif; ?>
                            </td>
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
                            <td style="padding: 10px;">
                                <?php if ((int) ($version['is_panel_runtime'] ?? 0) === 1): ?>
                                    <span class="badge">Panel</span>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Available PHP-FPM</h2>
            <?php if ($available === []): ?>
                <p class="muted" style="margin-bottom: 0;">No PHP-FPM versions were found in the configured APT repositories.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">Version</th>
                            <th style="text-align: left; padding: 10px;">Package</th>
                            <th style="text-align: left; padding: 10px;">Candidate</th>
                            <th style="text-align: left; padding: 10px;">Installed</th>
                            <th style="text-align: left; padding: 10px;">Sites</th>
                            <th style="text-align: right; padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available as $version): ?>
                            <tr>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['package'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($version['candidate'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px;">
                                    <?php if (!empty($version['installed'])): ?>
                                        <span class="badge">Installed</span>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px;"><?php echo (int) ($version['site_count'] ?? 0); ?></td>
                                <td style="padding: 10px; text-align: right;">
                                    <?php if (!empty($version['installed'])): ?>
                                        <?php if (!empty($version['can_uninstall'])): ?>
                                            <form method="post" action="/php-versions" style="display: inline-block;">
                                                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                                <input type="hidden" name="action" value="uninstall">
                                                <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="button" type="submit">Uninstall</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">
                                                <?php
                                                $reasons = [];

                                                if (!empty($version['is_panel_runtime'])) {
                                                    $reasons[] = 'panel';
                                                }

                                                if (!empty($version['is_default'])) {
                                                    $reasons[] = 'default';
                                                }

                                                if ((int) ($version['site_count'] ?? 0) > 0) {
                                                    $reasons[] = 'sites';
                                                }

                                                echo htmlspecialchars('In use: ' . implode(', ', $reasons), ENT_QUOTES, 'UTF-8');
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="post" action="/php-versions" style="display: inline-block;">
                                            <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                            <input type="hidden" name="action" value="install">
                                            <input type="hidden" name="version" value="<?php echo htmlspecialchars($version['version'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="button primary" type="submit">Install</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'PHP Versions - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
