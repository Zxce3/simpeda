<?php
function page_login(array &$opt)
{
    $opt['title'] = 'Login';
    $opt['content'] = displayLoginForm($GLOBALS['simpeda_login_error'] ?? '');
    
}

function displayLoginForm($error = '')
{
    ob_start();
    if (isUserAuthenticated()) {
        redirect('/dashboard');
        return;
    }
    if (!isUserAuthenticated()) {
?>
        <div class="container">
            <form class="card" method="POST" action="/login">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                <?php if ($error): ?>
                    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <input type="email" name="email" placeholder="Email" autocomplete="username" required />
                <input type="password" name="password" placeholder="Password" autocomplete="current-password" required />
                <input type="checkbox" name="remember"> Remember me
                <button type="submit">Login</button>
            </form>
        </div>
<?php
    }
    return ob_get_clean();
}
?>
