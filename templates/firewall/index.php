<?php
$statusOutput = (string) ($status['output'] ?? '');
$rulesOutput = (string) ($rules['output'] ?? '');
$ufwAvailable = (int) ($status['exit_code'] ?? 1) !== 3;
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
                <h1 style="margin: 0;">Firewall</h1>
                <div class="muted">Simple UFW controls. Admin only.</div>
            </div>
            <form method="post" action="/logout">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <button class="button" type="submit">Logout</button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <section class="card" style="margin-bottom: 16px;">
                <h2 style="margin-top: 0;">Result</h2>
                <p class="muted">Exit code: <?php echo (int) ($result['exit_code'] ?? 1); ?></p>
                <pre style="white-space: pre-wrap; overflow: auto; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars((string) ($result['output'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>
            </section>
        <?php endif; ?>

        <?php if (!$ufwAvailable): ?>
            <div class="alert">UFW is not installed on this server.</div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Status</h2>
            <pre style="white-space: pre-wrap; overflow: auto; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars($statusOutput, ENT_QUOTES, 'UTF-8'); ?></pre>
        </section>

        <section class="card" style="margin-bottom: 16px;">
            <h2 style="margin-top: 0;">Rules</h2>
            <pre style="white-space: pre-wrap; overflow: auto; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;"><?php echo htmlspecialchars($rulesOutput, ENT_QUOTES, 'UTF-8'); ?></pre>
        </section>

        <?php if ($ufwAvailable): ?>
            <section class="card" style="margin-bottom: 16px;">
                <h2 style="margin-top: 0;">Port Rule</h2>
                <form method="post" action="/firewall" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: end;">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <div class="field" style="margin: 0; min-width: 160px; flex: 1;">
                        <label for="port">Port</label>
                        <input id="port" name="port" inputmode="numeric" placeholder="443">
                    </div>
                    <div class="field" style="margin: 0; min-width: 120px;">
                        <label for="proto">Protocol</label>
                        <select id="proto" name="proto" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                            <option value="tcp">tcp</option>
                            <option value="udp">udp</option>
                        </select>
                    </div>
                    <button class="button" type="submit" name="action" value="allow">Allow</button>
                    <button class="button" type="submit" name="action" value="deny" onclick="return confirm('Deny this port?');">Deny</button>
                </form>
            </section>

            <section class="card" style="margin-bottom: 16px;">
                <h2 style="margin-top: 0;">Delete Rule</h2>
                <form method="post" action="/firewall" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: end; max-width: 420px;">
                    <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                    <input type="hidden" name="action" value="delete">
                    <div class="field" style="margin: 0; min-width: 160px; flex: 1;">
                        <label for="rule">Rule number</label>
                        <input id="rule" name="rule" inputmode="numeric" placeholder="1">
                    </div>
                    <button class="button" type="submit" onclick="return confirm('Delete this numbered UFW rule?');">Delete</button>
                </form>
            </section>

            <section class="card">
                <h2 style="margin-top: 0;">Firewall Power</h2>
                <p class="muted">Before enabling UFW, add SSH port 22 if you still need direct SSH access. CaddyPanel will automatically allow 80/tcp and 443/tcp on enable.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <form method="post" action="/firewall">
                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                        <input type="hidden" name="action" value="enable">
                        <button class="button" type="submit" onclick="return confirm('Enable UFW? 80/tcp and 443/tcp will be allowed automatically.');">Enable UFW</button>
                    </form>
                    <form method="post" action="/firewall">
                        <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                        <input type="hidden" name="action" value="disable">
                        <button class="button" type="submit" onclick="return confirm('Disable UFW? Existing rules will remain stored by UFW.');">Disable UFW</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Firewall - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
