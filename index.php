<?php
function getCPUUsage()
{
    $cpu_info = file('/proc/stat');
    $cpu_usage = 'N/A';
    if (!empty($cpu_info[0])) {
        preg_match_all('/\d+/', $cpu_info[0], $matches);
        $cpu_parts = array_map('intval', $matches[0]);
        if (count($cpu_parts) >= 10) {
            $total_cpu = array_sum($cpu_parts);
            if (isset($cpu_parts[3]) && is_numeric($cpu_parts[3]) && isset($total_cpu) && is_numeric($total_cpu) && $total_cpu != 0) {
                $cpu_usage = round(100 * ($total_cpu - $cpu_parts[3]) / $total_cpu, 2) . '%';
            }
        }
    }
    return $cpu_usage;
}

function getMemoryUsage()
{
    $memory_info = file_get_contents('/proc/meminfo');
    preg_match_all('/(\w+):\s+(\d+)/', $memory_info, $matches);
    $memory_data = array_combine($matches[1], $matches[2]);
    $memory_usage_gb = round(($memory_data['MemTotal'] - $memory_data['MemAvailable']) / (1024 * 1024), 2);
    $memory_total_gb = round($memory_data['MemTotal'] / (1024 * 1024), 2);
    return $memory_usage_gb . ' GB / ' . $memory_total_gb . ' GB';
}
function getDiskUsage()
{
    $disk_stats = file('/proc/diskstats', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $disk_info = [];

    foreach ($disk_stats as $line) {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 14 && $parts[2] !== 'ram' && $parts[2] !== 'loop') {
            $disk_name = $parts[2];
            $read_sectors = $parts[5];
            $write_sectors = $parts[9];
            $sectors_size = $parts[11];
            $disk_info[$disk_name] = [
                'Read Sectors' => $read_sectors,
                'Write Sectors' => $write_sectors,
                'Size (bytes)' => $sectors_size,
            ];
        }
    }
    $disk_usage = [];

    foreach ($disk_info as $disk_name => $info) {
        $disk_usage[] = "Disk: $disk_name - Read Sectors: {$info['Read Sectors']}, Write Sectors: {$info['Write Sectors']}, Size (bytes): {$info['Size (bytes)']}";
    }

    $disk_usage_string = implode("\n", $disk_usage);

    return $disk_usage_string;
}

function getDiskSpace()
{
    $disk_space_info = [
        '/' => ['Total' => disk_total_space('/'), 'Free' => disk_free_space('/')],
    ];

    $disk_space_str = '';
    foreach ($disk_space_info as $mount_point => $space) {
        $total_gb = round($space['Total'] / (1024 * 1024 * 1024), 2);
        $free_gb = round($space['Free'] / (1024 * 1024 * 1024), 2);
        $disk_space_str .= "$mount_point $free_gb GB / $total_gb GB\n";
    }

    return $disk_space_str;
}

function getUptime()
{
    $uptime = file_get_contents('/proc/uptime');
    $uptime_parts = explode(' ', $uptime);
    $uptime_seconds = (int) $uptime_parts[0];
    return formatUptime($uptime_seconds);
}

function getNetworkInterfaces()
{
    if (file_exists('/proc/net/dev')) {
        $network_interfaces = file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $network_data = array();
        foreach ($network_interfaces as $line) {
            if (strpos($line, ':') !== false) {
                list($iface, $data) = explode(':', $line, 2);
                $data = preg_split('/\s+/', trim($data));
                $network_data[$iface] = array(
                    'RX Bytes' => formatBytes($data[0]),
                    'RX Packets' => $data[1],
                    'RX Errors' => $data[2],
                    'RX Dropped' => $data[3],
                    'TX Bytes' => formatBytes($data[8]),
                    'TX Packets' => $data[9],
                    'TX Errors' => $data[10],
                    'TX Dropped' => $data[11]
                );
            }
        }
        return $network_data;
    } else {
        return 'N/A';
    }
}

function getProcessCount()
{
    $proc_dir = '/proc';
    $process_count = 0;

    if ($handle = opendir($proc_dir)) {
        while (false !== ($entry = readdir($handle))) {
            if (is_numeric($entry)) {
                $process_count++;
            }
        }
        closedir($handle);
    }

    return $process_count;
}

function getDetailedServerStats(): array
{
    return [
        'CPU Usage'          => getCPUUsage(),
        'Memory Usage'       => getMemoryUsage(),
        'Disk Usage'         => getDiskUsage(),
        'Disk Space'         => getDiskSpace(),
        'Uptime'             => getUptime(),
        'Network Interfaces' => getNetworkInterfaces(),
        'Process Count'      => getProcessCount()

    ];
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_stats') {
    header("Content-Type: application/json");
    echo json_encode(getDetailedServerStats());
    exit();
}
function formatUptime($uptime_seconds)
{
    $days = floor($uptime_seconds / (60 * 60 * 24));
    $hours = floor(($uptime_seconds % (60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($uptime_seconds % (60 * 60)) / 60);
    $seconds = $uptime_seconds % 60;
    $uptime = '';
    if ($days > 0) {
        $uptime .= "$days days, ";
    }
    if ($hours > 0) {
        $uptime .= "$hours hours, ";
    }
    if ($minutes > 0) {
        $uptime .= "$minutes minutes, ";
    }
    $uptime .= "$seconds seconds";
    return rtrim($uptime, ', ');
}
function formatBytes($bytes)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Dashboard</title>
    <link rel="shortcut icon" href="https://github.com/zxce3.png" type="image/x-icon" />
    <script>
        function updateStats() {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var stats = JSON.parse(this.responseText);
                    var container = document.getElementById('stats-container');
                    container.innerHTML = '';
                    for (var metric in stats) {
                        var card = document.createElement('div');
                        card.className = 'card';
                        var cardContent = document.createElement('div');
                        cardContent.className = 'card-content';
                        var title = document.createElement('h2');
                        title.className = 'card-title';
                        title.textContent = metric;
                        var content;
                        if (metric === 'Network Interfaces' && typeof(stats[metric]) === 'object') {
                            content = document.createElement('ul');
                            for (var iface in stats[metric]) {
                                var ifaceItem = document.createElement('li');
                                var ifaceButton = document.createElement('button');
                                ifaceButton.type = 'button';
                                ifaceButton.textContent = '<' + iface + '>';
                                ifaceButton.className = 'collapse-button';
                                ifaceButton.dataset.target = 'collapse-' + iface;
                                var ul = document.createElement('ul');
                                ul.id = 'collapse-' + iface;
                                ul.className = 'collapse-content';
                                for (var key in stats[metric][iface]) {
                                    var subListItem = document.createElement('li');
                                    subListItem.textContent = key + ': ' + stats[metric][iface][key];
                                    ul.appendChild(subListItem);
                                }
                                ifaceButton.addEventListener('click', function() {
                                    var target = document.getElementById(this.dataset.target);
                                    if (target.style.display === 'block') {
                                        target.style.display = 'none';
                                    } else {
                                        target.style.display = 'block';
                                    }
                                });
                                ifaceItem.appendChild(ifaceButton);
                                ifaceItem.appendChild(ul);
                                content.appendChild(ifaceItem);
                            }
                        } else if (typeof(stats[metric]) === 'object') {
                            content = document.createElement('ul');
                            for (var key in stats[metric]) {
                                var listItem = document.createElement('li');
                                listItem.textContent = key + ': ' + stats[metric][key];
                                content.appendChild(listItem);
                            }
                        } else {
                            content = document.createElement('p');
                            content.textContent = stats[metric];
                        }
                        cardContent.appendChild(title);
                        cardContent.appendChild(content);
                        card.appendChild(cardContent);
                        container.appendChild(card);
                    }
                }
            };
            xhttp.open("GET", "?action=get_stats&timestamp=" + new Date().getTime(), true);
            xhttp.send();
        }
        updateStats();
    </script>
</head>

<body>
    <script>
        function updateTime() {
            var now = new Date();
            var timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
        }
        setInterval(updateTime, 1000);
    </script>
    <div class="container">
        <h1>Server Dashboard
            <button class="btn" onclick="updateStats()">Update View</button>
        </h1>
        <span id="current-time"></span>
        <div id="stats-container" class="row"></div>
    </div>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #212B38;
            color: limegreen;
        }

        .btn {
            font-size: medium;
            background: #181818;
            color: white;
            padding: 10px;
            margin: 5px;
            border: 1px solid limegreen;
            border-radius: 5px;
            cursor: pointer;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 15px;
        }

        .row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            grid-gap: 15px;
        }

        .card {
            border: 3px solid green;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .card:hover {
            border: 5px solid limegreen;
            border-radius: 0;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-content {
            padding: 15px;
        }

        .card-content ul {
            list-style-type: none;
            padding: 0;
        }

        .card-content ul li {
            margin-bottom: 8px;
        }

        .card-content button {
            font-size: 1rem;
            font-weight: bold;
            text-align: center;
            padding: 0;
            border: none;
            background: none;
            color: green;
            cursor: pointer;
            outline: none;
        }

        .card-content button i {
            margin-left: 5px;
        }

        .collapse-content {
            padding-left: 20px;
            display: none;
        }
    </style>
</body>

</html>