<?php

function page_404(array &$opt)
{
    $opt['title'] = 'Page not found';
    $opt['content'] = '<div class="empty card"><h2>404 · Page not found</h2><p>The page you are looking for does not exist.</p><a class="btn primary" href="/">Go home</a></div>';
}

?>
