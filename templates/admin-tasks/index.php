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
                <h1 style="margin: 0;">Admin Tasks</h1>
                <div class="muted">Safe server actions and diagnostics. No arbitrary shell commands.</div>
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

        <section class="card" style="padding: 0; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Service</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--border);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <?php echo htmlspecialchars($service['label'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($service['service']) && $service['label'] !== $service['service']): ?>
                                    <div class="muted"><?php echo htmlspecialchars($service['service'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <?php echo htmlspecialchars($service['status'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <?php if (!empty($service['service'])): ?>
                                    <div style="display: inline-flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;">
                                        <?php
                                            $serviceOperations = strtolower((string) $service['status']) === 'active'
                                                ? ['stop' => 'Stop', 'restart' => 'Restart']
                                                : ['start' => 'Start', 'restart' => 'Restart'];
                                        ?>
                                        <?php foreach ($serviceOperations as $operation => $label): ?>
                                            <form method="post" action="/admin-tasks" style="display: inline;">
                                                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                                <input type="hidden" name="mode" value="service">
                                                <input type="hidden" name="service" value="<?php echo htmlspecialchars($service['service'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="operation" value="<?php echo htmlspecialchars($operation, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button
                                                    class="button"
                                                    type="submit"
                                                    <?php echo $operation === 'stop' ? "onclick=\"return confirm('Stop this service?');\"" : ''; ?>
                                                ><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
                                            </form>
                                        <?php endforeach; ?>

                                        <?php if ($service['service'] === 'caddy'): ?>
                                            <form method="post" action="/admin-tasks" style="display: inline;">
                                                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                                <input type="hidden" name="mode" value="service">
                                                <input type="hidden" name="service" value="caddy">
                                                <input type="hidden" name="operation" value="reload">
                                                <button class="button" type="submit">Reload</button>
                                            </form>
                                            <form method="post" action="/admin-tasks" style="display: inline;">
                                                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                                                <input type="hidden" name="mode" value="action">
                                                <input type="hidden" name="action" value="caddy-validate">
                                                <button class="button" type="submit">Validate</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">No detected service.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card" style="margin-top: 16px;">
            <h2 style="margin-top: 0;">Logs</h2>
            <form method="post" action="/admin-tasks" style="display: grid; gap: 12px; max-width: 560px;">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <input type="hidden" name="mode" value="logs">
                <div class="field" style="margin: 0;">
                    <label for="target">Log</label>
                    <select id="target" name="target" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php foreach ($logTargets as $target => $label): ?>
                            <option value="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin: 0;">
                    <label for="log_service">PHP-FPM service, optional</label>
                    <input id="log_service" name="service" placeholder="php8.3-fpm">
                </div>
                <div class="field" style="margin: 0;">
                    <label for="lines">Lines</label>
                    <input id="lines" name="lines" value="120">
                </div>
                <button class="button" type="submit">Read log</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Admin Tasks - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
