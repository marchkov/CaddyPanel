<?php ob_start(); ?>
<main style="min-height: 100vh; display: grid; place-items: center; padding: 20px;">
    <section class="card" style="width: 100%; max-width: 400px;">
        <h1 style="margin-top: 0;">CaddyPanel</h1>
        <p class="muted">Sign in to manage this server.</p>

        <?php if (!empty($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="/login">
            <?php echo \CaddyPanel\Core\Csrf::input(); ?>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="button primary" type="submit" style="width: 100%;">Login</button>
        </form>

        <p class="muted" style="margin-bottom: 0; margin-top: 14px;">Development login: admin / password123</p>
    </section>
</main>
<?php
$content = ob_get_clean();
$title = 'Login - CaddyPanel';
$theme = 'dark';
require dirname(__DIR__) . '/layouts/app.php';
