<?php
// Minimal PocketBase stand-in for smoke tests: health, password auth and token refresh.
function b64($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function jwt($sig) { return b64('{"alg":"HS256"}') . '.' . b64(json_encode(['exp' => time() + 3600])) . '.' . $sig; }
function out($data, $code = 200) { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); }

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($path === '/api/health') {
    out(['code' => 200]);
} elseif ($path === '/api/collections/_superusers/auth-with-password') {
    ($body['identity'] ?? '') === 'admin@example.com' && ($body['password'] ?? '') === 'p&ss<word>"1'
        ? out(['token' => jwt('valid-signature')])
        : out(['message' => 'Failed to authenticate.'], 400);
} elseif ($path === '/api/collections/_superusers/auth-refresh') {
    $revoked = ($f = getenv('STUB_REVOKED_FILE')) && file_exists($f);
    !$revoked && substr($auth, -15) === 'valid-signature' ? out(['token' => jwt('valid-signature')]) : out(['message' => 'Unauthorized'], 401);
} else {
    out(['message' => 'Not found'], 404);
}
