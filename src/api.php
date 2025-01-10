<?php

if (isset($_GET['/'])) {
    displayHome();
    exit;
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

if (file_exists('pocketbase/pb_data')) {
    if (isset($_GET['dashboard'])) {
        displayDashboard();
    } else {
        displayHome();
    }
} else {
    displayInstall();
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate email and password
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password)) {
        $arch = getSystemArch();
        $os = strtolower(PHP_OS);
        $version = '0.24.2';

        $url = "https://github.com/pocketbase/pocketbase/releases/download/v{$version}/pocketbase_{$version}_{$os}_{$arch}.zip";
        exec("wget $url");
        exec("unzip pocketbase_{$version}_{$os}_{$arch}.zip -d pocketbase");
        exec("rm pocketbase_{$version}_{$os}_{$arch}.zip");
        $output = [];
        exec("pocketbase/pocketbase superuser create $email $password", $output);
        echo "Pocketbase installed and user created successfully.";
        header('Location: dashboard.php');
        exit;
    } else {
        echo "Invalid email or password.";
    }
    exit;
}
?>
