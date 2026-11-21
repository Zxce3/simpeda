<?php

const POCKETBASE_VERSION = '0.40.6';
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
        // The first superuser is created from the command line (as the PocketBase docs describe),
        // never through an unauthenticated web form.
        sendResponse(['error' => 'Web installation is disabled. Run: php index.php install'], 403);
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
        throw new RuntimeException('Failed to download Pocketbase');
    }
    if (!verifyPocketbaseChecksum($filename, $version)) {
        unlink($filename);
        throw new RuntimeException('Pocketbase checksum verification failed');
    }

    $unzipCommand = sprintf('unzip -o %s -d %s', escapeshellarg($filename), escapeshellarg(POCKETBASE_PATH));
    exec($unzipCommand, $output, $return_var);
    if ($return_var !== 0) {
        unlink($filename);
        throw new RuntimeException('Failed to extract Pocketbase');
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
        throw new RuntimeException('Failed to start Pocketbase during installation');
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
        throw new RuntimeException('Failed to create superuser');
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


function promptLine($prompt)
{
    if (stream_isatty(STDIN)) {
        fwrite(STDERR, $prompt);
    }
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}

function promptPassword($prompt)
{
    $tty = stream_isatty(STDIN) && DIRECTORY_SEPARATOR === '/';
    if ($tty) {
        shell_exec('stty -echo');
        register_shutdown_function(static function () {
            shell_exec('stty echo');
        });
    }
    $password = promptLine($prompt);
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDERR, "\n");
    }
    return $password;
}

// Command line entry point: php index.php install [--email=EMAIL]
// The password is read from the terminal (hidden) or from stdin when piped.
function runCli(array $argv)
{
    $command = $argv[1] ?? 'help';
    if ($command !== 'install') {
        fwrite(STDERR, "Usage: php index.php install [--email=EMAIL]\n");
        return $command === 'help' ? 0 : 1;
    }

    chdir(__DIR__);
    if (isPocketbaseInstalled()) {
        fwrite(STDERR, "PocketBase is already installed (" . POCKETBASE_DATA_PATH . " exists).\n");
        return 1;
    }

    $email = null;
    foreach ($argv as $arg) {
        if (strncmp($arg, '--email=', 8) === 0) {
            $email = substr($arg, 8);
        }
    }
    $email = $email ?? promptLine('Superuser email: ');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "A valid email is required.\n");
        return 1;
    }

    $password = promptPassword('Password (min 8 characters): ');
    if (stream_isatty(STDIN) && promptPassword('Repeat password: ') !== $password) {
        fwrite(STDERR, "Passwords do not match.\n");
        return 1;
    }
    if (strlen($password) < 8) {
        fwrite(STDERR, "The password must be at least 8 characters.\n");
        return 1;
    }

    try {
        installPocketbase($email, $password);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Install failed: ' . $e->getMessage() . "\n");
        return 1;
    }
    echo "PocketBase " . POCKETBASE_VERSION . " installed. Log in to the dashboard as $email.\n";
    return 0;
}

if (PHP_SAPI === 'cli') {
    exit(runCli($argv ?? []));
}
handleRequest();

?>