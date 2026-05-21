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
                <h1 style="margin: 0;">Create User</h1>
                <div class="muted">Create admin or manager account.</div>
            </div>
            <a class="button" href="/users">Back</a>
        </div>

        <section class="card" style="max-width: 680px;">
            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/users/create">
                <?php echo \CaddyPanel\Core\Csrf::input(); ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" value="<?php echo htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role" style="width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                        <?php $selectedRole = $old['role'] ?? 'manager'; ?>
                        <option value="manager" <?php echo $selectedRole === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="admin" <?php echo $selectedRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                    <div class="muted">Minimum 8 characters.</div>
                </div>
                <button class="button primary" type="submit">Create user</button>
            </form>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Create User - CaddyPanel';
require dirname(__DIR__) . '/layouts/app.php';
