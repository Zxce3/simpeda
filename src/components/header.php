<?php

function displayHeader()
{
    ob_start();
?>
    <div class="header">
        <h1><a href="/"> Server Dashboard <span class="badge">V2.1</span></a></h1>
        <button class="theme-toggle" onclick="toggleTheme()">Toggle Theme</button>
        <?php if (isUserAuthenticated()): ?>
            <form method="POST" action="?logout" style="display:inline;">
                <button type="submit">Logout</button>
            </form>
        <?php else: ?>
            <button onclick="window.location.href='/login'">Login</button>
        <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}
?>