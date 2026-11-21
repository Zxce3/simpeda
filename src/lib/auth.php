<?php
class Auth
{
    const DEFAULT_POCKETBASE_URL = 'http://127.0.0.1:8090';
    const VERIFY_INTERVAL = 300;
    const TOKEN_COOKIE = 'auth_token';
    const REFRESH_INTERVAL = 3600; 
    const MAX_TOKEN_AGE = 30 * 24 * 60 * 60; 

    public static function pocketbaseUrl()
    {
        return getenv('SIMPEDA_POCKETBASE_URL') ?: self::DEFAULT_POCKETBASE_URL;
    }

    // Decodes the JWT payload only. This does NOT verify the signature; use verifyToken().
    private static function decodeToken($token)
    {
        $parts = is_string($token) ? explode('.', $token) : [];
        if (count($parts) !== 3) {
            return null;
        }
        $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = $decoded === false ? null : json_decode($decoded, true);
        return is_array($payload) ? $payload : null;
    }

    // Asks PocketBase to validate the token (signature and expiry) by refreshing it.
    public static function verifyToken($token)
    {
        $decoded = self::decodeToken($token);
        if (!$decoded || ($decoded['exp'] ?? 0) < time()) {
            return false;
        }
        try {
            $client = new PocketBaseClient(self::pocketbaseUrl(), $token);
            $response = $client->post('/api/collections/_superusers/auth-refresh', ['token' => $token]);
        } catch (Exception $e) {
            return false;
        }
        return isset($response['token']);
    }

    public static function refreshToken()
    {
        
        $token = $_SESSION['auth_token'] ?? $_COOKIE[self::TOKEN_COOKIE] ?? null;

        if ($token) {
            $client = new PocketBaseClient(self::pocketbaseUrl(), $token);
            $response = $client->post("/api/collections/_superusers/auth-refresh", ['token' => $token]);
            if (isset($response['token'])) {
                self::setAuthCookie($response['token'], true);
                return true;
            }
        }
        return false;
    }

    public static function setAuthCookie($token, $remember = false)
    {
        $decoded = self::decodeToken($token);
        if (!$decoded)
            return false;

        $expiry = $remember ? time() + self::MAX_TOKEN_AGE : 0;
        setcookie(self::TOKEN_COOKIE, $token, [
            'expires' => $expiry,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        $_SESSION['auth_token'] = $token;
        $_SESSION['token_expiry'] = $decoded['exp'] ?? 0;
        $_SESSION['auth_verified_at'] = time();

        return true;
    }

    public static function checkAuth()
    {
        
        $token = $_SESSION['auth_token'] ?? $_COOKIE[self::TOKEN_COOKIE] ?? null;

        if (!$token) {
            return false;
        }

        $expiry = $_SESSION['token_expiry'] ?? time();
        if (time() > $expiry) {
            return false;
        }

        return true;
    }

    public static function logout()
    {
        setcookie(self::TOKEN_COOKIE, '', [
            'expires' => 1,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_destroy();
    }

    public static function getCurrentUser()
    {
        $token = $_SESSION['auth_token'] ?? $_COOKIE[self::TOKEN_COOKIE] ?? null;

        if (!$token) {
            return null;
        }

        $decoded = self::decodeToken($token);
        if (!$decoded) {
            return null;
        }

        
        return $decoded['user'] ?? null;
    }

    public static function authenticateUser($email, $password)
    {
        $client = new PocketBaseClient(self::pocketbaseUrl());
        $response = $client->post("/api/collections/_superusers/auth-with-password", [
            'identity' => $email,
            'password' => $password
        ]);
        return $response['token'] ?? null;
    }

    
}

function handleLogin()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_GET['logout'])) {
            Auth::logout();
            header('Location: login');
            exit;
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        $token = Auth::authenticateUser($email, $password);
        if ($token) {
            Auth::setAuthCookie($token, $remember);
            session_regenerate_id(true);  
            header('Location: /dashboard');
            exit;
        } else {
            displayLoginForm('Invalid email or password.');
        }
    } else {
        displayLoginForm('');
    }
}

function isUserAuthenticated()
{
    $now = time();

    // Session already holds a token that PocketBase verified recently.
    if (isset($_SESSION['auth_token'], $_SESSION['auth_verified_at']) && Auth::checkAuth()
        && $now - $_SESSION['auth_verified_at'] < Auth::VERIFY_INTERVAL) {
        return true;
    }

    // Never trust a token (cookie or stale session) without asking PocketBase to verify it.
    $token = $_SESSION['auth_token'] ?? $_COOKIE[Auth::TOKEN_COOKIE] ?? null;
    if ($token && Auth::verifyToken($token)) {
        $decoded = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        $_SESSION['auth_token'] = $token;
        $_SESSION['token_expiry'] = $decoded['exp'] ?? 0;
        $_SESSION['auth_verified_at'] = $now;
        return true;
    }

    unset($_SESSION['auth_token'], $_SESSION['token_expiry'], $_SESSION['auth_verified_at']);
    return false;
}

handleLogin();
?>