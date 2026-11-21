<?php

function page_install(array &$opt)
{
    $opt['title'] = 'Install Pocketbase';
    $opt['content'] = displayInstall();
}

function displayInstall()
{
    ob_start();
    ?>
        <h1>Set up SIMPEDA</h1>
        <p>PocketBase is not installed yet. Create the first superuser from the command line on the server:</p>
        <pre><code>php index.php install</code></pre>
        <p>You will be asked for an email and a password (hidden). Reload this page when it finishes.</p>
    <?php
    return ob_get_clean();
}
?>