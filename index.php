<?php
function parseLine($line)
{
    $line_parts = explode(':', $line);
    if (count($line_parts) == 2) {
        $key = trim($line_parts[0]);
        $value = trim($line_parts[1]);
        return [$key, $value];
    }
    return [null, null];
}
function getCPUInfo()
{
    $cpu_info = file('/proc/cpuinfo');
    $cpu_model_name = '';
    $cpu_cores = 0;
    $cpu_clock_speed = '';
    foreach ($cpu_info as $line) {
        list($key, $value) = parseLine($line);
        if ($key !== null && $value !== null) {
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
function getLoadAverage()
{
    $load_avg = sys_getloadavg();
    return [
        '1 Minute' => $load_avg[0],
        '5 Minutes' => $load_avg[1],
        '15 Minutes' => $load_avg[2]
    ];
}
function getBasicServerInfo()
{
    $os_info = php_uname('s') . ' ' . php_uname('r');
    $kernel_version = php_uname('v');
    $hostname = gethostname();
    $php_version = phpversion();
    $web_server = $_SERVER['SERVER_SOFTWARE'];
    return [
        'Operating System' => $os_info,
        'Kernel Version' => $kernel_version,
        'Hostname' => $hostname,
        'PHP Version' => $php_version,
        'Web Server' => $web_server
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
            $cpu_usage[$cpu_name] = [
                'Usage' => round($usage, 2) . '%',
                'User' => $user,
                'Nice' => $nice,
                'System' => $system,
                'Idle' => $idle,
                'I/O Wait' => $iowait,
                'IRQ' => $irq,
                'SoftIRQ' => $softirq,
                'Steal' => $steal,
                'Guest' => $guest,
                'Guest Nice' => $guest_nice,
            ];
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
                $iface = trim($iface);
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
        'Basic Info'          => getBasicServerInfo(),
        'Network Interfaces' => getNetworkInterfaces(),
        'CPU Usage'          => getCPUUsage(),
        'CPU Info'          => getCPUInfo(),
        'Disk Space'         => getDiskSpace(),
        'Memory Usage'       => getMemoryUsage(),
        'Load Avarage'       => getLoadAverage(),
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_theme' && isset($_POST['theme'])) {
        $theme = $_POST['theme'];
        $themes = [
            'dark',
            'minimalist',
            'ocean-blue',
            'classic-gray',
            'high-contrast',
            'retro-vibes'
        ];
        if (in_array($theme, $themes)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid theme']);
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server <?php echo gethostname(); ?></title>
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
            if (metric === 'Network Interfaces' || metric === 'CPU Usage' && typeof data === 'object') {
                content = document.createElement('ul');
                for (var iface in data) {
                    var ifaceItem = document.createElement('li');
                    var ifaceButton = document.createElement('button');
                    ifaceButton.type = 'button';
                    ifaceButton.textContent = '<' + iface + '>';
                    ifaceButton.className = 'collapse-button btn';
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

<body class="theme-dark">
    <script>
        function updateTime() {
            var now = new Date();
            var timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
        }

        function changeTheme(theme) {
            var selectedTheme = theme;
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var response = JSON.parse(this.responseText);
                    if (response.success) {
                        document.body.className = 'theme-' + selectedTheme;
                        localStorage.setItem('selected-theme', selectedTheme);
                    } else {
                        alert('Error changing theme: ' + response.error);
                    }
                }
            };
            xhttp.open("POST", "", true);
            xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhttp.send("action=change_theme&theme=" + selectedTheme);
        }

        function toggleThemeDropdown() {
            var themeSelect = document.getElementById('theme-selector');
            themeSelect.style.display = (themeSelect.style.display === 'block') ? 'none' : 'block';
        }

        function initializeTheme() {
            var selectedTheme = localStorage.getItem('selected-theme');
            if (selectedTheme) {
                document.body.className = 'theme-' + selectedTheme;
                var themeOptions = document.getElementsByClassName('theme-option');
                for (var i = 0; i < themeOptions.length; i++) {
                    if (themeOptions[i].dataset.theme === selectedTheme) {
                        themeOptions[i].classList.add('selected');
                    }
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
            document.addEventListener('click', function(event) {
                var themeSelect = document.getElementById('theme-selector');
                if (event.target.closest('.theme-button') === null && event.target.closest('.theme-select') === null) {
                    themeSelect.style.display = 'none';
                }
            });
        });
        document.addEventListener('click', function(event) {
            var themeOption = event.target.closest('.theme-option');
            if (themeOption) {
                var theme = themeOption.dataset.theme;
                if (theme) {
                    changeTheme(theme);
                    var themeOptions = document.getElementsByClassName('theme-option');
                    for (var i = 0; i < themeOptions.length; i++) {
                        themeOptions[i].classList.remove('selected');
                    }
                    themeOption.classList.add('selected');
                }
            }
        });
        setInterval(updateTime, 1000);
    </script>
    <div class="container">
        <h1>Server Dashboard
            <button class="btn" onclick="updateStats()">Update View</button>
            <span id="current-time"></span>
        </h1>
        <div class="theme-dropdown">
            <button class="theme-button btn" onclick="toggleThemeDropdown()">Theme</button>
            <div id="theme-selector" class="theme-select">
                <div class="theme-option" data-theme="dark">Dark Mode</div>
                <div class="theme-option" data-theme="minimalist">Minimalist</div>
                <div class="theme-option" data-theme="ocean-blue">Ocean Blue</div>
                <div class="theme-option" data-theme="classic-gray">Classic Gray</div>
                <div class="theme-option" data-theme="high-contrast">High Contrast</div>
                <div class="theme-option" data-theme="retro-vibes">Retro Vibes</div>
            </div>
        </div>
        <div id="stats-container" class="row"></div>
    </div>
    <style>
        .theme-dropdown {
            position: relative;
            display: inline-block;
        }

        .theme-button {
            padding: 10px 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
            cursor: pointer;
        }

        .theme-button:hover {
            background-color: #ccc;
        }

        .theme-select {
            display: none;
            position: absolute;
            background-color: #fff;
            min-width: 160px;
            font-size: medium;
            border-radius: 5px;
            padding: 5px;
            margin: 1px;
            color: initial;
            cursor: pointer;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1;
        }

        .theme-option {
            padding: 10px;
            cursor: pointer;
        }

        .theme-option.selected {
            border: 1px solid black;
        }

        .theme-option:hover {
            background-color: rgba(0, 0, 0, 0.2);
        }

        .theme-dark {
            background-color: #212B38;
            color: limegreen;
            border-color: #2ECC71;
        }

        .theme-dark hr {
            border-color: limegreen;
        }

        .theme-dark .btn {
            background: #363636;
            border: 1px solid limegreen;
        }

        .theme-dark .card {
            border: 3px solid limegreen;
            border-radius: 8px;
        }

        .theme-minimalist {
            background-color: #ffffff;
            color: #000000;
            border-color: #2ECC71;
        }

        .theme-minimalist hr {
            border-color: #000000;
        }

        .theme-minimalist .btn {
            background: #363636;
            border: 1px solid #000000;
        }

        .theme-minimalist .card {
            border: 3px solid #000000;
            border-radius: 0;
        }

        .theme-ocean-blue {
            background-color: #007BFF;
            color: #ffffff;
            border-color: #2ECC71;
        }

        .theme-ocean-blue hr {
            border-color: aqua;
        }

        .theme-ocean-blue .btn {
            background: #363636;
            border: 1px solid #ffffff;
        }

        .theme-ocean-blue .card {
            border: 3px solid aqua;
            border-radius: 15px;
        }

        .theme-classic-gray {
            background-color: #808080;
            color: #ffffff;
            border-color: #2ECC71;
        }

        .theme-classic-gray hr {
            border-color: #ffffff;
        }

        .theme-classic-gray .btn {
            background: #363636;
            border: 1px solid #ffffff;
        }

        .theme-classic-gray .card {
            border: 3px solid #ffffff;
            border-radius: 8px;
        }

        .theme-high-contrast {
            background-color: #000000;
            color: #ffffff;
            border-color: #fff;
        }

        .theme-high-contrast hr {
            border-color: #fff;
        }

        .theme-high-contrast .btn {
            background: #363636;
            border: 1px solid #ffffff;
        }

        .theme-high-contrast .card {
            border: 3px solid #ffffff;
            border-radius: 8px;
        }

        .theme-retro-vibes {
            background-color: #8B0000;
            color: #ffffff;
            border-color: #2ECC71;
        }

        .theme-retro-vibes hr {
            border-color: #2ECC71;
        }

        .theme-retro-vibes .btn {
            background: #363636;
            border: 1px solid #ffffff;
        }

        .theme-retro-vibes .card {
            border: 3px solid #ffffff;
            border-radius: 8px;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        hr {
            border: 1px solid limegreen;
        }

        .btn {
            font-size: medium;
            border-radius: 5px;
            padding: 5px;
            margin: 1px;
            color: inherit;
            cursor: pointer;
            background: none !important;
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
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            border: none !important;
            background: none !important;
            cursor: pointer;
            outline: none !important;
        }

        .card-content button i {
            margin-left: 5px;
        }

        .collapse-content {
            background-color: rgba(0, 0, 0, 0.2);
            padding-left: 20px !important;
            display: none;
        }
    </style>
</body>

</html>
