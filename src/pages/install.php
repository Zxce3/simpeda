<?php

function page_install(array &$opt)
{
    $opt['title'] = 'Set up';
    $opt['content'] = displayInstall();
}

function displayInstall()
{
    ob_start();
    ?>
        <div class="auth-wrap">
            <div class="card auth-card" style="width:min(560px,100%)">
                <h1>Set up SIMPEDA</h1>
                <p class="muted">PocketBase is not installed yet. Create the first superuser from the command line on the server:</p>
                <pre><code>php index.php install</code></pre>
                <p class="muted">You will be asked for an email and a password (hidden). Reload this page when it finishes.</p>
            </div>
        </div>
    <?php
    return ob_get_clean();
}
?>
