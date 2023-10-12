<?php
function getCPUInfo()
{
    $cpu_info = file('/proc/cpuinfo');
    $cpu_model_name = '';
    $cpu_cores = 0;
    $cpu_clock_speed = '';
    foreach ($cpu_info as $line) {
        $line_parts = explode(':', $line);
        if (count($line_parts) == 2) {
            $key = trim($line_parts[0]);
            $value = trim($line_parts[1]);
            if ($key == 'model name') {
                $cpu_model_name = $value;
            } else if ($key == 'cpu cores') {
                $cpu_cores = intval($value);
            } else if ($key == 'cpu MHz') {
                $cpu_clock_speed = $value . ' MHz';
            }
        }
    }
    return [
        'Model Name' => $cpu_model_name,
        'Cores' => $cpu_cores,
        'Clock Speed' => $cpu_clock_speed,
    ];
}
function getCPUUsage()
{
    $cpu_usage = [];
    $cpu_stat = file('/proc/stat');
    foreach ($cpu_stat as $line) {
        $line_parts = preg_split('/\s+/', $line);
        if (count($line_parts) > 4 && substr($line_parts[0], 0, 3) == 'cpu') {
            $cpu_name = $line_parts[0];
            $user = intval($line_parts[1]);
            $nice = intval($line_parts[2]);
            $system = intval($line_parts[3]);
            $idle = intval($line_parts[4]);
            $iowait = intval($line_parts[5]);
            $irq = intval($line_parts[6]);
            $softirq = intval($line_parts[7]);
            $steal = intval($line_parts[8]);
            $guest = intval($line_parts[9]);
            $guest_nice = intval($line_parts[10]);
            $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq + $steal + $guest + $guest_nice;
            $usage = 100 - ($idle * 100 / $total);
            $cpu_usage[$cpu_name] = formatBytes($usage) . '%';
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
    $df_output = shell_exec('df -B 1G');
    $df_lines = explode("\n", $df_output);
    $disk_space = [];
    foreach ($df_lines as $line) {
        $line = preg_replace('/\s+/', ' ', $line);
        $line_parts = explode(' ', $line);
        if (count($line_parts) == 6 && $line_parts[0] != 'Filesystem') {
            $mount_point = $line_parts[5];
            $disk_space[$mount_point] = [
                'total' => $line_parts[1] * 1024 * 1024 * 1024,
                'used' => $line_parts[2] * 1024 * 1024 * 1024,
                'free' => $line_parts[3] * 1024 * 1024 * 1024,
            ];
        }
    }
    $disk_space_info_str = '';
    foreach ($disk_space as $mount_point => $space) {
        $total_gb = round($space['total'] / (1024 * 1024 * 1024), 2);
        $free_gb = round($space['free'] / (1024 * 1024 * 1024), 2);
        $used_gb = round($space['used'] / (1024 * 1024 * 1024), 2);
        $used_percent = round($space['used'] / $space['total'] * 100, 2);
        $disk_space_info_str .= "$mount_point $free_gb GB / $total_gb GB ($used_percent%)\n";
    }
    return $disk_space_info_str;
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
                    'RX Bytes' => $data[0] . ' (' . formatBytes($data[0]) . ')',
                    'RX Packets' => $data[1],
                    'RX Errors' => $data[2],
                    'RX Dropped' => $data[3],
                    'TX Bytes' => $data[8] . ' (' . formatBytes($data[8]) . ')',
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
        'CPU Info'          => getCPUInfo(),
        'Network Interfaces' => getNetworkInterfaces(),
        'Disk Space'         => getDiskSpace(),
        'Memory Usage'       => getMemoryUsage(),
        'Uptime'             => getUptime(),
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
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log(abs($bytes)) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * abs($pow)));
    $bytes = round($bytes, $precision);
    return ($bytes < 0 ? '-' : '') . abs($bytes) . ' ' . $units[$pow];
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
        function createCard(title, content) {
            var card = document.createElement('div');
            card.className = 'card';
            var cardContent = document.createElement('div');
            cardContent.className = 'card-content';
            var titleElement = document.createElement('h2');
            titleElement.className = 'card-title';
            titleElement.textContent = title;
            cardContent.appendChild(titleElement);
            cardContent.appendChild(content);
            card.appendChild(cardContent);
            return card;
        }

        function createList(data) {
            var list = document.createElement('ul');
            for (var key in data) {
                var listItem = document.createElement('li');
                listItem.textContent = key + ': ' + data[key];
                list.appendChild(listItem);
            }
            return list;
        }

        function createDiskSpaceContent() {
            var diskSpaceInfo = `<?php echo getDiskSpace(); ?>`;
            var content = document.createElement('p');
            content.innerHTML = diskSpaceInfo.replace(/\n/g, '<br/><hr/>');
            return content;
        }

        function createContent(metric, data) {
            var content;
            if (metric === 'Network Interfaces' && typeof data === 'object') {
                content = document.createElement('ul');
                for (var iface in data) {
                    var ifaceItem = document.createElement('li');
                    var ifaceButton = document.createElement('button');
                    ifaceButton.type = 'button';
                    ifaceButton.textContent = '<' + iface + '>';
                    ifaceButton.className = 'collapse-button';
                    ifaceButton.dataset.target = 'collapse-' + iface;
                    var ul = document.createElement('ul');
                    ul.id = 'collapse-' + iface;
                    ul.className = 'collapse-content';
                    for (var key in data[iface]) {
                        var subListItem = document.createElement('li');
                        subListItem.textContent = key + ': ' + data[iface][key];
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
            } else if (typeof data === 'object') {
                content = createList(data);
            } else {
                if (metric === 'Disk Space') {
                    content = createDiskSpaceContent();
                } else {
                    content = document.createElement('p');
                    content.textContent = data;
                }
            }
            return content;
        }

        function updateStats() {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var stats = JSON.parse(this.responseText);
                    var container = document.getElementById('stats-container');
                    container.innerHTML = '';
                    for (var metric in stats) {
                        var content = createContent(metric, stats[metric]);
                        var card = createCard(metric, content);
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

        hr {
            border: 1px solid limegreen;
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
            grid-auto-rows: minmax(100px, auto);
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
