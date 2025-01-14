<?php



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

    if ($method === 'GET') {
        handleGetRequests($path, $query);
    } elseif ($method === 'POST') {
        handlePostRequests($path);
    } else {
        sendResponse(['error' => 'Method not allowed'], 405);
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
    $isHome = ($path === '/');

    if (!isPocketbaseInstalled()) {
        if (isset($_GET['email']) && isset($_GET['password'])) {
            $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
            $password = htmlspecialchars($_GET['password'], ENT_QUOTES, 'UTF-8');
            installPocketbase($email, $password);
            redirect('?login');
        } else {
            displayInstallForm();
        }
        return;
    }

    if (isPocketbaseInstalled() && !isPocketbaseRunning()) {
        $port = findAvailablePort();
        if ($port) {
            startPocketbase($port);
            sleep(2); 
        }
    }

    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'basic' => SystemInformation::getBasicInfo(),
            'cpu' => SystemInformation::getCPUInfo(),
            'memory' => SystemInformation::getMemoryInfo(),
            'disk' => SystemInformation::getDiskInfo(),
            'uptime' => SystemInformation::getUptime(),
            'load' => SystemInformation::getLoadAverage(),
            'network' => SystemInformation::getNetworkInfo(),
            'processes' => SystemInformation::getProcessList(),
            'timestamp' => date('Y-m-d H:i:s T')
        ]);
        exit;
    }

    if (!empty($query)) {
        switch ($query) {
            case 'status':
                handlePocketbaseStatus();
                break;
            case 'dashboard':
                if (!isUserAuthenticated()) {
                    redirect('?login');
                    return;
                }
                displayDashboard();
                break;
            case 'login':
                if (isUserAuthenticated()) {
                    redirect('?dashboard');
                    return;
                }
                if (!$isHome) {
                    displayLoginForm();
                }
                break;
            default:
                displayErrorPage();
                break;
        }
    } else {
        switch ($path) {
            case '/':
                displayHome();
                break;
            default:
                displayErrorPage();
                break;
        }
    }
}

function displayInstallForm()
{
    echo '<form method="GET" action="/">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Install Pocketbase</button>
          </form>';
}

function handlePostRequests($path)
{
    if ($path === '?install') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = htmlspecialchars($_POST['password'] ?? '', ENT_QUOTES, 'UTF-8');
        installPocketbase($email, $password);
        sendResponse(['message' => 'Installation successful'], 200);
    } elseif ($path === '?login') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = htmlspecialchars($_POST['password'] ?? '', ENT_QUOTES, 'UTF-8');
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

function installPocketbase($email, $password)
{
    $arch = getSystemArch();
    $os = strtolower(PHP_OS);
    $version = '0.24.2';
    $filename = "pocketbase_{$version}_{$os}_{$arch}.zip";
    $url = "https://github.com/pocketbase/pocketbase/releases/download/v{$version}/$filename";

    $downloaded = file_put_contents($filename, file_get_contents($url));
    if (!$downloaded) {
        sendResponse(['error' => 'Failed to download Pocketbase'], 500);
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
        escapeshellcmd(POCKETBASE_PATH),
        (int) DEFAULT_PORT,
        escapeshellcmd(POCKETBASE_DATA_PATH),
        escapeshellcmd(POCKETBASE_LOG)
    );
    exec($command);
    sleep(5); // Wait for Pocketbase to start

    $command = escapeshellcmd(POCKETBASE_PATH . "/pocketbase superuser create " .
        escapeshellarg($email) . " " .
        escapeshellarg($password));
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        sendResponse(['error' => 'Failed to create superuser'], 500);
    }
    createUser($email, $password); 
    return true;
}

function createUser($email, $password)
{
    $client = new PocketBaseClient('http://127.0.0.1:' . DEFAULT_PORT);
    $response = $client->post("/api/collections/users/records", [
        'email' => $email,
        'password' => $password,
        'passwordConfirm' => $password,
        'name' => 'User'
    ]);
    if (!isset($response['id'])) {
        sendResponse(['error' => 'Failed to create user'], 500);
    }
}

function startPocketbase($port)
{
    $command = sprintf(
        'nohup %s/pocketbase serve --http="127.0.0.1:%d" --dir=%s > %s 2>&1 &',
        escapeshellcmd(POCKETBASE_PATH),
        (int) $port,
        escapeshellcmd(POCKETBASE_DATA_PATH),
        escapeshellcmd(POCKETBASE_LOG)
    );
    exec($command);
    sleep(5); // Wait for Pocketbase to start
}

function sendResponse($data, $status = 200)
{
    header("HTTP/1.1 " . $status);
    
    echo json_encode($data);
}


handleRequest();

?>