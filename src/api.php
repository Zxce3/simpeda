<?php

const POCKETBASE_VERSION = '0.24.2';
const POCKETBASE_PATH = 'pocketbase';
const POCKETBASE_DATA_PATH = 'pocketbase/pb_data';
const POCKETBASE_LOG = 'pocketbase.log';
const DEFAULT_PORT = 8090;
const MAX_PORT = 8100;


function handleRequest()
{
    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    $parsedUri = parse_url($uri);
    $path = $parsedUri['path'] ?? '/';
    $query = $parsedUri['query'] ?? '';

    try {
        if (!isPocketbaseInstalled() && $path !== '/install') {
            redirect('/install');
        }

        if ($method === 'GET') {
            handleGetRequests($path, $query);
        } elseif ($method === 'POST') {
            handlePostRequests($path);
        } else {
            sendResponse(['error' => 'Method not allowed'], 405);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        sendResponse(['error' => 'Internal Server Error'], 500);
    }
}
function redirect($location)
{
    header("Location: $location");
    exit;
}
function isPocketbaseInstalled()
{
    return file_exists(POCKETBASE_DATA_PATH);
}
function handleGetRequests($path, $query)
{
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        if (!isUserAuthenticated()) {
            sendResponse(['error' => 'Unauthorized'], 401);
            exit;
        }
        echo json_encode([
            'basic' => SystemInformation::getBasicInfo(),
            'cpu' => SystemInformation::getCPUInfo(),
            'memory' => SystemInformation::getMemoryInfo(),
            'disk' => SystemInformation::getDiskInfo(),
            'uptime' => SystemInformation::getUptime(),
            'load' => SystemInformation::getLoadAverage(),
            'network' => SystemInformation::getNetworkInfo(),
            'processes' => SystemInformation::getProcessList(),
            'swap' => SystemInformation::getSwapInfo(),
            'users' => SystemInformation::getUserInfo(),
            'security' => SystemInformation::getSecurityInfo(),
            'logs' => SystemInformation::getLogInfo(),
            'temperature' => SystemInformation::getTemperatureInfo(),
            'timestamp' => date('Y-m-d H:i:s T')
        ]);
        exit;
    }

    if (!empty($query)) {
        switch ($query) {
            case 'status':
                handlePocketbaseStatus();
                return;
            case 'dashboard':
                if (!isUserAuthenticated()) {
                    redirect('/login');
                    return;
                }
                if (!web_router('dashboard')) {
                    displayErrorPage();
                }
                return;
            case 'login':
                if (isUserAuthenticated()) {
                    redirect('/dashboard');
                    return;
                }
                if (!web_router('login')) {
                    displayErrorPage();
                }
                return;
            default:
                displayErrorPage();
                return;
        }
    }

    if (!isPocketbaseInstalled()) {
        web_router('install');
        return;
    }

    if (isPocketbaseInstalled() && !isPocketbaseRunning()) {
        $port = findAvailablePort();
        if ($port) {
            startPocketbase($port);
            sleep(2);
        }
    }

    if ($path === '/install' && isPocketbaseInstalled() && isPocketbaseRunning()) {
        redirect('/');
        return;
    }

    if (!web_router(ltrim($path, '/'))) {
        displayErrorPage();
    }
}

function handlePostRequests($path)
{
    if ($path === '/install') {
        // Without this guard anyone could create a new superuser on an installed instance.
        if (isPocketbaseInstalled()) {
            sendResponse(['error' => 'Already installed'], 409);
            return;
        }
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || strlen($password) < 8) {
            sendResponse(['error' => 'A valid email and a password of at least 8 characters are required'], 400);
            return;
        }
        installPocketbase($email, $password);
        sendResponse(['message' => 'Installation successful'], 200);
    } elseif ($path === '/login') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        $token = Auth::authenticateUser($email, $password);
        if ($token) {
            Auth::setAuthCookie($token, $remember);
            session_regenerate_id(true);
            sendResponse(['message' => 'Login successful'], 200);
        } else {
            sendResponse(['message' => 'Invalid email or password'], 401);
        }
    } else {
        sendResponse(['message' => 'Invalid POST request'], 404);
    }
}

function handlePocketbaseStatus()
{
    if (!file_exists(POCKETBASE_DATA_PATH)) {
        sendResponse(['error' => 'Pocketbase is not installed'], 404);
        return;
    }

    if (!isPocketbaseRunning()) {
        $port = findAvailablePort();
        if (!$port) {
            sendResponse(['error' => 'No available ports found'], 503);
            return;
        }

        startPocketbase($port);
        sendResponse([
            'message' => 'Pocketbase was not running. Starting it up...',
            'port' => $port
        ], 202);
        return;
    }

    sendResponse(['message' => 'Pocketbase is running'], 200);
}

function isPocketbaseRunning()
{
    try {
        $ch = curl_init("http://127.0.0.1:" . DEFAULT_PORT . "/api/health");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true
        ]);
        curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

function getSystemArch()
{
    $uname = php_uname('m');
    return match ($uname) {
        'x86_64' => 'amd64',
        'aarch64' => 'arm64',
        'armv7l' => 'armv7',
        'ppc64le' => 'ppc64le',
        's390x' => 's390x',
        default => 'amd64',
    };
}

function isPortInUse($port)
{
    $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

function findAvailablePort($startPort = DEFAULT_PORT, $maxPort = MAX_PORT)
{
    for ($port = $startPort; $port <= $maxPort; $port++) {
        $connection = @fsockopen('127.0.0.1', $port);
        if (!$connection) {
            return $port;
        }
        fclose($connection);
    }
    return false;
}

function verifyPocketbaseChecksum($filename, $version)
{
    $sums = @file_get_contents("https://github.com/pocketbase/pocketbase/releases/download/v{$version}/checksums.txt");
    if (!$sums) {
        return false;
    }
    foreach (explode("\n", $sums) as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) === 2 && $parts[1] === $filename) {
            return hash_equals(strtolower($parts[0]), hash_file('sha256', $filename));
        }
    }
    return false;
}

function installPocketbase($email, $password)
{
    $arch = getSystemArch();
    $os = strtolower(PHP_OS);
    $version = POCKETBASE_VERSION;
    $filename = "pocketbase_{$version}_{$os}_{$arch}.zip";
    $url = "https://github.com/pocketbase/pocketbase/releases/download/v{$version}/$filename";

    $downloaded = file_put_contents($filename, file_get_contents($url));
    if (!$downloaded) {
        sendResponse(['error' => 'Failed to download Pocketbase'], 500);
    }
    if (!verifyPocketbaseChecksum($filename, $version)) {
        unlink($filename);
        sendResponse(['error' => 'Pocketbase checksum verification failed'], 500);
    }

    $unzipCommand = sprintf('unzip -o %s -d %s', escapeshellarg($filename), escapeshellarg(POCKETBASE_PATH));
    exec($unzipCommand, $output, $return_var);
    if ($return_var !== 0) {
        unlink($filename);
        sendResponse(['error' => 'Failed to extract Pocketbase'], 500);
    }
    unlink($filename);

    $command = sprintf(
        '%s/pocketbase serve --http="127.0.0.1:%d" --dir=%s > %s 2>&1 &',
        escapeshellarg(POCKETBASE_PATH),
        (int) DEFAULT_PORT,
        escapeshellarg(POCKETBASE_DATA_PATH),
        escapeshellarg(POCKETBASE_LOG)
    );
    exec($command);
    sleep(5);

    if (!isPocketbaseRunning()) {
        error_log('Failed to start Pocketbase during installation');
        sendResponse(['error' => 'Failed to start Pocketbase during installation'], 500);
    }

    // escapeshellcmd() on top of escapeshellarg() mangled special characters in the password.
    $command = sprintf(
        '%s superuser create %s %s',
        escapeshellarg(POCKETBASE_PATH . '/pocketbase'),
        escapeshellarg($email),
        escapeshellarg($password)
    );
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        sendResponse(['error' => 'Failed to create superuser'], 500);
    }

    $client = new PocketBaseClient(Auth::pocketbaseUrl());
    $client->authWithPassword($email, $password);
    createSystemInformationSchema($client, $email, $password);

    return true;
}

function startPocketbase($port)
{
    $command = sprintf(
        'nohup %s/pocketbase serve --http="127.0.0.1:%d" --dir=%s > %s 2>&1 &',
        escapeshellarg(POCKETBASE_PATH),
        (int) $port,
        escapeshellarg(POCKETBASE_DATA_PATH),
        escapeshellarg(POCKETBASE_LOG)
    );
    exec($command);
    sleep(5);

    if (!isPocketbaseRunning()) {
        error_log('Failed to start Pocketbase on port ' . $port);
        sendResponse(['error' => 'Failed to start Pocketbase'], 500);
    }
}

function sendResponse($data, $status = 200)
{
    header("HTTP/1.1 " . $status);

    echo json_encode($data);
}


handleRequest();

?>