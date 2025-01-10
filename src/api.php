<?php

// Constants
const POCKETBASE_PATH = 'pocketbase';
const POCKETBASE_DATA_PATH = 'pocketbase/pb_data';
const POCKETBASE_LOG = 'pocketbase.log';
const DEFAULT_PORT = 8090;
const MAX_PORT = 8100;

function handleRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequests();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequests();
    } else {
        sendResponse(['error' => 'Method not allowed'], 405);
    }
}

function handleGetRequests() {
    if (isset($_GET['/'])) {
        displayHome();
        return;
    }

    if (isset($_GET['api'])) {
        sendResponse([
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
        return;
    }

    handlePocketbaseStatus();
}

function handlePostRequests() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
        sendResponse(['error' => 'Invalid email or password'], 400);
        return;
    }

    try {
        installPocketbase($email, $password);
        sendResponse(['message' => 'Pocketbase installed successfully'], 201);
    } catch (Exception $e) {
        sendResponse(['error' => $e->getMessage()], 500);
    }
}

function handlePocketbaseStatus() {
    if (!file_exists(POCKETBASE_DATA_PATH)) {
        displayInstall();
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

    if (isset($_GET['dashboard'])) {
        displayDashboard();
    } else {
        displayHome();
    }
}

function isPocketbaseRunning() {
    try {
        $ch = curl_init("http://127.0.0.1:8090/api/health");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
    } catch (Exception $e) {
        return false;
    }
}

function getSystemArch() {
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

function isPortInUse($port) {
    $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

function findAvailablePort($startPort = DEFAULT_PORT, $maxPort = MAX_PORT) {
    for ($port = $startPort; $port <= $maxPort; $port++) {
        if (!isPortInUse($port)) {
            return $port;
        }
    }
    return false;
}

function installPocketbase($email, $password) {
    $arch = getSystemArch();
    $os = strtolower(PHP_OS);
    $version = '0.24.2';
    $filename = "pocketbase_{$version}_{$os}_{$arch}.zip";
    $url = "https://github.com/pocketbase/pocketbase/releases/download/v{$version}/$filename";

    // Download and extract with error checking
    $downloaded = file_put_contents($filename, file_get_contents($url));
    if (!$downloaded) {
        throw new Exception('Failed to download Pocketbase');
    }

    $zip = new ZipArchive;
    if ($zip->open($filename) !== true) {
        unlink($filename);
        throw new Exception('Failed to extract Pocketbase');
    }
    
    $zip->extractTo(POCKETBASE_PATH);
    $zip->close();
    unlink($filename);

    // Create superuser
    $command = escapeshellcmd(POCKETBASE_PATH . "/pocketbase superuser create " . 
                             escapeshellarg($email) . " " . 
                             escapeshellarg($password));
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception('Failed to create superuser');
    }

    startPocketbase(DEFAULT_PORT);
}

function startPocketbase($port) {
    $command = sprintf(
        'nohup %s/pocketbase serve --http="127.0.0.1:%d" > %s 2>&1 &',
        escapeshellcmd(POCKETBASE_PATH),
        (int)$port,
        escapeshellcmd(POCKETBASE_LOG)
    );
    exec($command);
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Start request handling
handleRequest();

?>
