<?php

function displayHeader()
{
    $authed = isUserAuthenticated();
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $links = $authed
        ? [['dashboard', '/dashboard', 'Servers'], ['monitors', '/monitors', 'Monitors'], ['settings', '/settings', 'Settings'], ['status', '/status', 'Status page']]
        : [['status', '/status', 'Status']];
    $active = $path === 'server' ? 'dashboard' : $path;

    ob_start();
?>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="<?= $authed ? '/dashboard' : '/status' ?>"><span class="brand-dot"></span>SIMPEDA <span class="badge"><?= e(appVersion()) ?></span></a>
            <nav class="nav" aria-label="Main">
                <?php foreach ($links as [$key, $href, $label]): ?>
                    <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="topbar-actions">
                <button type="button" class="icon" id="theme-toggle" aria-label="Toggle theme" title="Toggle theme">◐</button>
                <?php if ($authed): ?>
                    <form method="POST" action="/?logout">
                        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                        <button type="submit">Logout</button>
                    </form>
                <?php else: ?>
                    <a class="btn" href="/login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
<?php
    return ob_get_clean();
}
?>
