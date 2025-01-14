<?php

function displayDashboard()
{
    if (!file_exists('pocketbase/pb_data')) {
        displayInstall();
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Server Dashboard</title>
        <?php getJavascriptCode(); ?>
        <?php getCssStyle(); ?>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Server Dashboard <span class="badge">V2.1</span></h1>
                <button class="theme-toggle" onclick="toggleTheme()">Toggle Theme</button>
                <form method="POST" action="?logout" style="display:inline;">
                    <button type="submit">Logout</button>
                </form>
            </div>
            <div class="grid" id="dashboard">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
