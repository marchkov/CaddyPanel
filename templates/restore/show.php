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
                <h1 style="margin: 0;">Restore Backup</h1>
                <div class="muted"><?php echo htmlspecialchars($backup['domain'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a class="button" href="/backups">Back</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <section class="card" style="margin-bottom: 16px;">
                <h2 style="margin-top: 0;">Restore Result</h2>
                <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
            </section>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Inspection</h2>
            <p><span class="muted">File:</span> <?php echo htmlspecialchars($backup['backup_file'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Available:</span> <?php echo $inspection['exists'] ? 'yes' : 'no'; ?></p>
            <p class="muted"><?php echo htmlspecialchars($inspection['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <pre style="overflow: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars(json_encode($inspection['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
        </section>

        <section class="card" style="max-width: 680px;">
            <h2 style="margin-top: 0;">Confirmation</h2>
            <p class="muted">CaddyPanel creates a pre-restore backup before applying selected restore modes. Database restore requires SQL dumps in the backup archive.</p>
            <form method="post" action="/restore/<?php echo (int) $backup['id']; ?>">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <div class="field">
                    <label><input type="checkbox" name="restore_files" value="1" style="width: auto;"> Restore files</label>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="restore_database" value="1" style="width: auto;"> Restore database</label>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="restore_host_config" value="1" style="width: auto;"> Restore host config</label>
                </div>
                <button class="button primary" type="submit">Apply restore</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Restore Backup - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
