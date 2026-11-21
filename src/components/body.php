<?php

function generate_body(string $content, string $title): string
{
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> · SIMPEDA</title>
    <?php if (isUserAuthenticated()): ?>
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <?php endif; ?>
    <?php getThemeCode(); ?>
    <?php getCssStyle(); ?>
</head>
<body>
    <?= displayHeader() ?>
    <main><?= $content ?></main>
    <div class="toasts" id="toasts" aria-live="polite"></div>
    <?php getJavascriptCode(); ?>
</body>
</html>
<?php
    return ob_get_clean();
}
?>
