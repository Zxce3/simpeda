<?php
function load_page(string $__page)
{
    ob_start();
    web_router($__page);
    $__out = ob_get_clean();
    $opt = [];
    load_body($__out, $opt);
}

function web_router(string $uri): bool
{
    $uri = trim($uri, "/");
    $uri = explode("?", $uri)[0];

    do {
        $uri = str_replace("..", "", $uri, $n);
    } while ($n);

    if ($uri === "") {
        $uri = "status";
    }

    $routes = [
        'login' => 'page_login',
        'dashboard' => 'page_dashboard',
        'server' => 'page_server',
        'monitors' => 'page_monitors',
        'settings' => 'page_settings',
        'status' => 'page_status',
        'install' => 'page_install',
    ];

    if (array_key_exists($uri, $routes)) {
        $opt = [];
        $routes[$uri]($opt);
        load_body($opt['content'] ?? '', $opt);
        return true;
    }

    http_response_code(404);
    $opt = [];
    page_404($opt);
    load_body($opt['content'] ?? '', $opt);
    return false;
}

function load_body(string $content = "", array $opt = [])
{
    // A page may set 'content' to null (e.g. it echoes instead of returning), so fall back to the argument.
    echo generate_body((string) ($opt['content'] ?? $content), (string) ($opt['title'] ?? ''));
}

// Per-request nonce for the inline <script> tags allowed by the Content-Security-Policy.
function cspNonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

function sendSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'nonce-" . cspNonce() . "'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, "UTF-8");
}
?>
