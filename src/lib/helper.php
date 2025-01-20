<?php
function load_page(string $__page)
{
    ob_start();
    web_router($__page);
    $__out = ob_get_clean();
    $opt = [];
    load_body($__out ?? '', $opt);
}

function web_router(string $uri): bool
{
    $uri = trim($uri, "/");
    $uri = explode("?", $uri)[0];

    do {
        $uri = str_replace("..", "", $uri, $n);
    } while ($n);

    if ($uri === "") {
        $uri = "home";
    }

    $routes = [
        'home' => 'page_home',
        'login' => 'page_login',
        'dashboard' => 'page_dashboard',
        'install' => 'page_install',
    ];

    if (array_key_exists($uri, $routes)) {
        $opt = [];
        $routes[$uri]($opt);
        load_body($opt['content'] ?? '', $opt);
        return true;
    }

    $opt = [];
    page_404($opt);
    load_body($opt['content'] ?? '', $opt);
    return false;
}

function load_body(string $content = "", array $opt = [])
{
    extract($opt);
    echo generate_body($content ?? '', $title ?? '');
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, "UTF-8");
}
?>
