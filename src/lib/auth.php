<?php
class Auth
{
    const POCKETBASE_URL = 'http://127.0.0.1:8090';
    const TOKEN_COOKIE = 'auth_token';
    const REFRESH_INTERVAL = 3600; 
    const MAX_TOKEN_AGE = 30 * 24 * 60 * 60; 

    private static function decodeToken($token)
    {
        
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[1]))), true);
    }

    public static function refreshToken()
    {
        
        $token = $_SESSION['auth_token'] ?? $_COOKIE[self::TOKEN_COOKIE] ?? null;

        if ($token) {
            $client = new PocketBaseClient(self::POCKETBASE_URL, $token);
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
        $client = new PocketBaseClient(self::POCKETBASE_URL);
        $response = $client->post("/api/collections/_superusers/auth-with-password", [
            'identity' => $email,
            'password' => $password
        ]);
        return $response['token'] ?? null;
    }

    
}

function handleLogin()
{
    $isHome = ($_SERVER['REQUEST_URI'] === '/');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_GET['logout'])) {
            Auth::logout();
            header('Location: login');
            exit;
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = htmlspecialchars($_POST['password'] ?? '', ENT_QUOTES, 'UTF-8');
        $remember = isset($_POST['remember']);

        $token = Auth::authenticateUser($email, $password);
        if ($token) {
            Auth::setAuthCookie($token, $remember);
            session_regenerate_id(true);  
            header('Location: /dashboard');
            exit;
        } else {
            displayLoginForm('Invalid email or password.', $isHome);
        }
    } else {
        displayLoginForm('', $isHome);
    }
}

function isUserAuthenticated()
{
    if (isset($_SESSION['auth_token']) && Auth::checkAuth()) {
        return true;
    }

    
    if (isset($_COOKIE[Auth::TOKEN_COOKIE])) {
        $token = $_COOKIE[Auth::TOKEN_COOKIE];
        if (Auth::setAuthCookie($token, true)) {
            return true;
        }
    }

    return false;
}

handleLogin();
?>