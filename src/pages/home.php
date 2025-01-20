<?php

function page_home(array &$opt)
{
    $opt['title'] = 'Home Pages';
    $opt['content'] = displayHome();
}

function displayHome()
{
    ob_start();
    ?>
    <div class="container">
        <div class="welcome-message">
            <h2>Welcome to the Server Dashboard</h2>
            <p>This dashboard provides an overview of your server's performance and status. Use the navigation menu to access different sections.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
