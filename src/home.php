<?php

function displayHome()
{
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Server Dashboard</title>
    </head>
    <?php getCssStyle(); ?>
    <body>
    <div class="container">
            <div class="header">
                <h1>Server Dashboard <span class="badge">V2.1</span></h1>
                <button class="theme-toggle" onclick="toggleTheme()">Toggle Theme</button>
            </div>
            <a class="theme-toggle" href="?dashboard">Go to Dashboard</a>
        </div>
    </body>

    </html>
    <?php
}
?>