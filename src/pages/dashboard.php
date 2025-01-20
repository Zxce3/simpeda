<?php

function page_dashboard(array &$opt)
{
    $opt['title'] = 'Dashboard';
    $opt['content'] = displayDashboard();
}

function displayDashboard()
{
    if (!file_exists('pocketbase/pb_data')) {
        displayInstall();
        exit;
    }
    ob_start();
    ?>
        <?php getJavascriptCode(); ?>
            <div class="grid" id="dashboard">
                <!-- Content will be loaded via JavaScript -->
            </div>
    <?php
    return ob_get_clean();
}
?>
