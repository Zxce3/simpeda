<?php

// API endpoint for AJAX updates
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
?>
