<?php

const POCKETBASE_VERSION = '0.40.6';
// Keep this directory out of the web root (or deny it in the web server): it holds the
// database and the service credentials. SIMPEDA_PB_DIR relocates it.
function pocketbasePath()
{
    return rtrim(getenv('SIMPEDA_PB_DIR') ?: 'pocketbase', '/');
}

function pocketbaseDataPath()
{
    return pocketbasePath() . '/pb_data';
}

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
        if (strncmp($path, '/api/', 5) === 0) {
            if (!isPocketbaseInstalled()) {
                sendResponse(['error' => 'Not installed. Run: php index.php install'], 503);
                return;
            }
            ensurePocketbaseRunning();
            handleApi($method, $path);
            return;
        }

        if (!isPocketbaseInstalled() && $path !== '/install') {
            redirect('/install');
        }

        if ($method === 'GET' && $path === '/download/agent') {
            serveAgentScript();
        } elseif ($method === 'GET') {
            handleGetRequests($path, $query);
        } elseif ($method === 'POST') {
            handlePostRequests($path);
        } else {
            sendResponse(['error' => 'Method not allowed'], 405);
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        sendResponse(['error' => 'Internal Server Error'], 500);
    }
}

// The agent is this same file, so remote servers can fetch it from the dashboard.
function serveAgentScript()
{
    header('Content-Type: text/x-php; charset=utf-8');
    header('Content-Disposition: attachment; filename="simpeda.php"');
    readfile(__FILE__);
}

function ensurePocketbaseRunning()
{
    if (isPocketbaseInstalled() && !isPocketbaseRunning()) {
        $port = findAvailablePort();
        if ($port) {
            startPocketbase($port);
        }
    }
}

function redirect($location)
{
    header("Location: $location");
    exit;
}
function isPocketbaseInstalled()
{
    return file_exists(pocketbaseDataPath());
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
                web_router('dashboard');
                return;
            case 'login':
                if (isUserAuthenticated()) {
                    redirect('/dashboard');
                    return;
                }
                web_router('login');
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

    ensurePocketbaseRunning();

    if ($path === '/install' && isPocketbaseInstalled() && isPocketbaseRunning()) {
        redirect('/');
        return;
    }

    $page = trim($path, '/');
    if (in_array($page, ['dashboard', 'server', 'monitors', 'settings'], true) && !isUserAuthenticated()) {
        redirect('/login');
    }
    if ($page === '') {
        redirect(isUserAuthenticated() ? '/dashboard' : '/status');
    }

    // web_router() renders the 404 page itself for unknown routes.
    web_router(ltrim($path, '/'));
}

function handlePostRequests($path)
{
    if (isset($_GET['logout'])) {
        if (csrfValid()) {
            Auth::logout();
        }
        redirect('/login');
    }

    if ($path === '/install') {
        // The first superuser is created from the command line (as the PocketBase docs describe),
        // never through an unauthenticated web form.
        sendResponse(['error' => 'Web installation is disabled. Run: php index.php install'], 403);
    } elseif ($path === '/login') {
        if (!csrfValid()) {
            $GLOBALS['simpeda_login_error'] = 'Your session expired. Please try again.';
            http_response_code(400);
            web_router('login');
            return;
        }
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        $token = Auth::authenticateUser($email, $password);
        if ($token && Auth::setAuthCookie($token, $remember)) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            redirect('/dashboard');
        }
        $GLOBALS['simpeda_login_error'] = 'Invalid email or password.';
        http_response_code(401);
        web_router('login');
    } else {
        sendResponse(['message' => 'Invalid POST request'], 404);
    }
}

function handlePocketbaseStatus()
{
    if (!file_exists(pocketbaseDataPath())) {
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

    $unzipCommand = sprintf('unzip -o %s -d %s', escapeshellarg($filename), escapeshellarg(pocketbasePath()));
    exec($unzipCommand, $output, $return_var);
    if ($return_var !== 0) {
        unlink($filename);
        throw new RuntimeException('Failed to extract Pocketbase');
    }
    unlink($filename);

    $command = sprintf(
        '%s/pocketbase serve --http="127.0.0.1:%d" --dir=%s > %s 2>&1 &',
        escapeshellarg(pocketbasePath()),
        (int) DEFAULT_PORT,
        escapeshellarg(pocketbaseDataPath()),
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
        escapeshellarg(pocketbasePath() . '/pocketbase'),
        escapeshellarg($email),
        escapeshellarg($password)
    );
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        throw new RuntimeException('Failed to create superuser');
    }

    $client = new PocketBaseClient(Auth::pocketbaseUrl());
    $client->authWithPassword($email, $password);
    migrateSchema($client);

    return true;
}

function startPocketbase($port)
{
    $command = sprintf(
        'nohup %s/pocketbase serve --http="127.0.0.1:%d" --dir=%s > %s 2>&1 &',
        escapeshellarg(pocketbasePath()),
        (int) $port,
        escapeshellarg(pocketbaseDataPath()),
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
    http_response_code($status);
    header('Content-Type: application/json');
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

function parseCliArgs(array $argv)
{
    $args = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $arg, $m)) {
            $args[$m[1]] = $m[2] ?? true;
        }
    }
    return $args;
}

function cliUsage()
{
    return <<<TXT
Usage: php index.php <command> [options]

  install [--email=EMAIL]      create the first superuser, download and set up PocketBase
  migrate [--email=EMAIL]      create/upgrade collections and the service account (after upgrades)
  worker                       run service monitors, alerts and data pruning (keep running)
  agent --url=URL --token=T    report this machine's metrics to a dashboard
        [--interval=5] [--once]

TXT;
}

// Reads email/password for an existing or new superuser (hidden prompt, or stdin when piped).
function promptCredentials(array $args, $confirm)
{
    $email = isset($args['email']) && is_string($args['email']) ? $args['email'] : promptLine('Superuser email: ');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid email is required.');
    }
    $password = promptPassword($confirm ? 'Password (min 8 characters): ' : 'Password: ');
    if ($confirm && stream_isatty(STDIN) && promptPassword('Repeat password: ') !== $password) {
        throw new InvalidArgumentException('Passwords do not match.');
    }
    if ($confirm && strlen($password) < 8) {
        throw new InvalidArgumentException('The password must be at least 8 characters.');
    }
    return [$email, $password];
}

function runCli(array $argv)
{
    $command = $argv[1] ?? 'help';
    $args = parseCliArgs($argv);
    chdir(__DIR__);

    try {
        switch ($command) {
            case 'install':
                if (isPocketbaseInstalled()) {
                    fwrite(STDERR, "PocketBase is already installed (" . pocketbaseDataPath() . " exists).\n");
                    return 1;
                }
                [$email, $password] = promptCredentials($args, true);
                installPocketbase($email, $password);
                echo "PocketBase " . POCKETBASE_VERSION . " installed. Log in to the dashboard as $email.\n";
                return 0;

            case 'migrate':
                if (!isPocketbaseInstalled()) {
                    fwrite(STDERR, "PocketBase is not installed. Run: php index.php install\n");
                    return 1;
                }
                [$email, $password] = promptCredentials($args, false);
                ensurePocketbaseRunning();
                $client = new PocketBaseClient(Auth::pocketbaseUrl());
                $client->authWithPassword($email, $password);
                migrateSchema($client);
                echo "Schema is up to date.\n";
                return 0;

            case 'worker':
                return runWorker();

            case 'agent':
                return runAgent($args);

            case 'help':
                echo cliUsage();
                return 0;

            default:
                fwrite(STDERR, cliUsage());
                return 1;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, $command . ' failed: ' . $e->getMessage() . "\n");
        return 1;
    }
}

if (PHP_SAPI === 'cli') {
    exit(runCli($argv ?? []));
}
handleRequest();

?>