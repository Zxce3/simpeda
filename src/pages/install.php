<?php

function page_install(array &$opt)
{
    $opt['title'] = 'Install Pocketbase';
    $opt['content'] = displayInstall();
}

function displayInstall()
{

    ?>
        <h1>Install Pocketbase</h1>
        <form method="POST">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <br>
            <button type="submit">Install</button>
        </form>
    <?php
}
?>