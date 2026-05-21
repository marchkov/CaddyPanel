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
                <h1 style="margin: 0;">Settings</h1>
                <div class="muted">Admin-only global settings.</div>
            </div>
            <form method="post" action="/logout">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <button class="button" type="submit">Logout</button>
            </form>
        </div>

        <section class="card" style="max-width: 760px;">
            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/settings">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>

                <h2 style="margin-top: 0;">Panel</h2>
                <div class="field">
                    <label for="panel_domain">Panel domain</label>
                    <input id="panel_domain" name="panel_domain" value="<?php echo htmlspecialchars($settings['panel_domain'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label for="admin_email">Admin email</label>
                    <input id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field">
                    <label for="ui_theme">UI theme</label>
                    <select id="ui_theme" name="ui_theme" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <option value="dark" <?php echo $settings['ui_theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                        <option value="light" <?php echo $settings['ui_theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                    </select>
                </div>

                <h2>PHP</h2>
                <div class="field">
                    <label for="default_php_version">Default PHP version</label>
                    <input id="default_php_version" name="default_php_version" value="<?php echo htmlspecialchars($settings['default_php_version'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label for="default_php_fpm_socket">Default PHP-FPM socket</label>
                    <input id="default_php_fpm_socket" name="default_php_fpm_socket" value="<?php echo htmlspecialchars($settings['default_php_fpm_socket'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <h2>Backups</h2>
                <div class="field">
                    <label for="backup_retention_count">Automatic backups to keep</label>
                    <input id="backup_retention_count" name="backup_retention_count" value="<?php echo htmlspecialchars($settings['backup_retention_count'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <h2>Security</h2>
                <div class="field">
                    <label for="session_lifetime">Session timeout, seconds</label>
                    <input id="session_lifetime" name="session_lifetime" value="<?php echo htmlspecialchars($settings['session_lifetime'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="muted">Allowed range: 300-86400.</div>
                </div>
                <div class="field">
                    <label for="security_ip_allowlist">Panel IP allowlist</label>
                    <textarea id="security_ip_allowlist" name="security_ip_allowlist" rows="3" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars($settings['security_ip_allowlist'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div class="muted">Leave empty to allow any IP. Use comma-separated IPs or CIDR ranges.</div>
                </div>
                <div class="field">
                    <label for="health_check_token">Health check token</label>
                    <input id="health_check_token" name="health_check_token" value="<?php echo htmlspecialchars($settings['health_check_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="muted">When empty, /health is available only from localhost.</div>
                </div>

                <h2>Updates</h2>
                <div class="field">
                    <label for="updates_branch">Updates branch</label>
                    <input id="updates_branch" name="updates_branch" value="<?php echo htmlspecialchars($settings['updates_branch'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="updates_auto_check" value="1" style="width: auto;" <?php echo $settings['updates_auto_check'] === '1' ? 'checked' : ''; ?>>
                        Enable automatic update checks
                    </label>
                </div>

                <h2>Modules</h2>
                <p class="muted">Disabled modules disappear from navigation and direct routes show a disabled-module page.</p>

                <?php foreach ($modules as $module): ?>
                    <div class="field">
                        <label>
                            <input
                                type="checkbox"
                                name="module[<?php echo htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8'); ?>]"
                                value="1"
                                style="width: auto;"
                                <?php echo (int) $module['enabled'] === 1 ? 'checked' : ''; ?>
                                <?php echo $module['name'] === 'settings' ? 'disabled checked' : ''; ?>
                            >
                            <?php echo htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button class="button primary" type="submit">Save settings</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Settings - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
