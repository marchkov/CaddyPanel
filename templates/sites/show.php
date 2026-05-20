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
                <h1 style="margin: 0;"><?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="muted">Status: <?php echo htmlspecialchars($site['status'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a class="button" href="/sites">Back</a>
                <a class="button" href="/sites/<?php echo (int) $site['id']; ?>/edit">Edit</a>
                <a class="button" href="/logs/sites/<?php echo (int) $site['id']; ?>">Logs</a>
                <a class="button" href="/sites/<?php echo (int) $site['id']; ?>/delete">Delete</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="grid">
            <div class="card">
                <div class="muted">Type</div>
                <div class="metric"><?php echo htmlspecialchars($site['type'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="card">
                <div class="muted">PHP</div>
                <div class="metric"><?php echo (int) $site['php_enabled'] === 1 ? 'on' : 'off'; ?></div>
            </div>
            <div class="card">
                <div class="muted">PHP version</div>
                <div class="metric"><?php echo htmlspecialchars($site['php_version'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Paths</h2>
            <p><span class="muted">Root:</span> <?php echo htmlspecialchars($site['root_path'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Public:</span> <?php echo htmlspecialchars($site['public_path'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Private:</span> <?php echo htmlspecialchars($site['private_path'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Logs:</span> <?php echo htmlspecialchars($site['logs_path'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Tmp:</span> <?php echo htmlspecialchars($site['tmp_path'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Caddy config:</span> <?php echo htmlspecialchars($site['caddy_config_path'], ENT_QUOTES, 'UTF-8'); ?></p>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Aliases</h2>
            <?php if (empty($site['aliases'])): ?>
                <p class="muted">No aliases.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($site['aliases'] as $alias): ?>
                        <li><?php echo htmlspecialchars($alias['domain'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Linked Databases</h2>
            <?php if (empty($site['databases'])): ?>
                <p class="muted">No linked databases.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid var(--border);">Name</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid var(--border);">User</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid var(--border);">Status</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid var(--border);">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($site['databases'] as $database): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid var(--border);"><a href="/databases/<?php echo (int) $database['id']; ?>"><?php echo htmlspecialchars($database['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                <td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($database['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($database['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid var(--border);"><?php echo htmlspecialchars($database['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if (!empty($site['last_error'])): ?>
            <section class="card" style="margin-top: 16px;">
                <h2 style="margin-top: 0;">Last Operation Message</h2>
                <p class="muted"><?php echo htmlspecialchars($site['last_error'], ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php endif; ?>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Caddy Config</h2>
            <p class="muted">Current config from <?php echo htmlspecialchars($site['caddy_config_path'], ENT_QUOTES, 'UTF-8'); ?>. Saving validates and reloads Caddy.</p>
            <form method="post" action="/sites/<?php echo (int) $site['id']; ?>/caddy-config" onsubmit="return confirm('Replace the active Caddy config for this site?');">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(\CaddyPanel\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <textarea
                    name="caddy_config"
                    rows="24"
                    style="width: 100%; box-sizing: border-box; overflow: auto; white-space: pre; font-family: ui-monospace, SFMono-Regular, Consolas, Liberation Mono, monospace; font-size: 13px; line-height: 1.45; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"
                    spellcheck="false"
                ><?php echo htmlspecialchars($site['caddy_config'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <label style="display: flex; gap: 8px; align-items: center; margin-top: 12px;">
                    <input type="checkbox" name="confirm_caddy_config" value="1">
                    <span>I understand this will replace the active Caddy config for this site.</span>
                </label>
                <div style="margin-top: 12px;">
                    <button type="submit">Save Caddy config</button>
                </div>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = $site['domain'] . ' - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
