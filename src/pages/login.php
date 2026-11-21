<?php
function page_login(array &$opt)
{
    $opt['title'] = 'Login';
    $opt['content'] = displayLoginForm($GLOBALS['simpeda_login_error'] ?? '');
}

function displayLoginForm($error = '')
{
    if (isUserAuthenticated()) {
        redirect('/dashboard');
        return '';
    }
    ob_start();
?>
    <div class="auth-wrap">
        <form class="card auth-card" method="POST" action="/login">
            <h1>Sign in</h1>
            <p class="muted">Use your PocketBase superuser account.</p>
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <?php if ($error): ?>
                <p class="error-text" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" required autofocus>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <label class="check"><input type="checkbox" name="remember"> Remember me</label>
            <button class="primary" type="submit" style="width:100%;justify-content:center">Sign in</button>
        </form>
    </div>
<?php
    return ob_get_clean();
}
?>
