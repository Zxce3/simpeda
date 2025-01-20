<?php

function generate_body(string $content, string $title): string
{
    ob_start();
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
        <?php getCssStyle(); ?>
        <?php getThemeCode(); ?>
    </head>

    <body id="index" class="home">
        <?php echo displayHeader() ?>
        <div id="wrapper">
            <aside id="featured" class="body">
                <article>
                    <?= $content; ?>
                </article>
            </aside>
        </div>
    </body>

    </html>
<?php
    return ob_get_clean();
}
?>