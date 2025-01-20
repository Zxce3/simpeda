<?php
function page_login(array &$opt)
{
    $opt['title'] = 'Login';
    $opt['content'] = displayLoginForm();
    
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
            <form class="card" method="POST" action="?login">
                <?php if ($error): ?>
                    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                <input type="checkbox" name="remember"> Remember me
                <button type="submit">Login</button>
            </form>
        </div>
<?php
    }
    return ob_get_clean();
}
?>
