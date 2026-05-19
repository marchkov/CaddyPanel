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
                <h1 style="margin: 0;">Edit Site</h1>
                <div class="muted"><?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a class="button" href="/sites/<?php echo (int) $site['id']; ?>">Back</a>
        </div>

        <section class="card" style="max-width: 760px;">
            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/sites/<?php echo (int) $site['id']; ?>/edit">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>

                <div class="field">
                    <label>Domain</label>
                    <input value="<?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    <div class="muted" style="margin-top: 6px;">Domain rename is not enabled yet. Create a new site if the primary domain must change.</div>
                </div>

                <div class="field">
                    <label>
                        <input type="checkbox" name="add_www_alias" value="1" style="width: auto;" <?php echo !empty($old['add_www_alias']) ? 'checked' : ''; ?>>
                        Add www alias
                    </label>
                </div>

                <div class="field">
                    <label for="aliases">Aliases</label>
                    <textarea id="aliases" name="aliases" rows="4" placeholder="alias1.com&#10;alias2.example.com" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars($old['aliases'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="field">
                    <label for="type">Type</label>
                    <select id="type" name="type" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php $selectedType = $old['type'] ?? $site['type']; ?>
                        <option value="php" <?php echo $selectedType === 'php' ? 'selected' : ''; ?>>PHP</option>
                        <option value="static" <?php echo $selectedType === 'static' ? 'selected' : ''; ?>>Static</option>
                    </select>
                </div>

                <div class="field">
                    <label for="php_version">PHP version</label>
                    <?php $selectedPhpVersion = $old['php_version'] ?? ($site['php_version'] ?: ($phpVersions[0]['version'] ?? '8.4')); ?>
                    <select id="php_version" name="php_version" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php foreach ($phpVersions as $phpVersion): ?>
                            <option value="<?php echo htmlspecialchars($phpVersion['version'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedPhpVersion === $phpVersion['version'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($phpVersion['version'] . ' - ' . $phpVersion['fpm_socket'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="muted" style="margin-top: 6px;">Saving also re-creates missing directories and reapplies the Caddy config.</div>
                </div>

                <button class="button primary" type="submit">Save changes</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Edit Site - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
