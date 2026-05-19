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
                <h1 style="margin: 0;"><?php echo htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8'); ?> Logs</h1>
                <div class="muted"><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?> log</div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a class="button" href="/logs/sites/<?php echo (int) $site['id']; ?>?type=access">Access</a>
                <a class="button" href="/logs/sites/<?php echo (int) $site['id']; ?>?type=error">Error</a>
                <a class="button" href="/logs">Back</a>
            </div>
        </div>

        <section class="card" style="margin-bottom: 16px;">
            <p><span class="muted">Path:</span> <?php echo htmlspecialchars($log['path'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><span class="muted">Available:</span> <?php echo $log['exists'] ? 'yes' : 'no'; ?></p>
            <p><span class="muted">Size:</span> <?php echo $log['size'] === null ? '-' : number_format((int) $log['size']) . ' bytes'; ?></p>
            <p class="muted"><?php echo htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8'); ?></p>
        </section>

        <section class="card">
            <pre style="min-height: 360px; max-height: 70vh; overflow: auto; white-space: pre-wrap; word-break: break-word; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px;"><?php echo htmlspecialchars($log['content'], ENT_QUOTES, 'UTF-8'); ?></pre>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = $site['domain'] . ' Logs - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
