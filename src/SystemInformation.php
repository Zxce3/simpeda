<?php
class SystemInformation
{
    private static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function getBasicInfo()
    {
        return [
            'Hostname' => gethostname(),
            'OS' => php_uname('s') . ' ' . php_uname('r'),
            'PHP Version' => phpversion(),
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ];
    }

    public static function getCPUInfo()
    {
        if (!file_exists('/proc/cpuinfo') || !file_exists('/proc/stat'))
            return ['CPU Info' => 'Not available'];
        $cpu_info = file_get_contents('/proc/cpuinfo');
        preg_match('/model name\s+:\s+(.+)$/m', $cpu_info, $model);
        preg_match_all('/^processor\s+:\s+\d+$/m', $cpu_info, $cores);
        $stat_info = file_get_contents('/proc/stat');
        preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat_info, $cpu_usage);
        $total_time = array_sum(array_slice($cpu_usage, 1));
        $idle_time = $cpu_usage[4];
        $usage_percentage = round((($total_time - $idle_time) / $total_time) * 100, 2);
        return [
            'Model' => $model[1] ?? 'Unknown',
            'Cores' => count($cores[0]),
            'Active Cores' => count($cores[0]),
            'CPU Usage' => $usage_percentage . '%'
        ];
    }

    public static function getMemoryInfo()
    {
        if (!file_exists('/proc/meminfo'))
            return ['Memory Info' => 'Not available'];
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        $total = isset($total[1]) ? (int) $total[1] * 1024 : 0;
        $available = isset($available[1]) ? (int) $available[1] * 1024 : 0;
        $used = $total - $available;
        return [
            'Total' => self::formatBytes($total),
            'Used' => self::formatBytes($used),
            'Available' => self::formatBytes($available),
            'Usage' => round(($used / $total) * 100, 2) . '%'
        ];
    }

    public static function getDiskInfo()
    {
        $disks = [];
        $df = shell_exec('df -B1');
        if (!$df)
            return ['Disk Info' => 'Not available'];
        foreach (explode("\n", $df) as $line) {
            if (preg_match('/^\/dev\//', $line)) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 6) {
                    $mount = $parts[5];
                    $disks[$mount] = [
                        'Total' => self::formatBytes((int) $parts[1]),
                        'Used' => self::formatBytes((int) $parts[2]),
                        'Available' => self::formatBytes((int) $parts[3]),
                        'Usage' => $parts[4]
                    ];
                }
            }
        }
        return $disks;
    }

    public static function getUptime()
    {
        if (!file_exists('/proc/uptime'))
            return 'Not available';
        $uptime = (int) file_get_contents('/proc/uptime');
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        return sprintf("%d days, %d hours, %d minutes", $days, $hours, $minutes);
    }

    public static function getLoadAverage()
    {
        $load = sys_getloadavg();
        return [
            '1min' => number_format($load[0], 2),
            '5min' => number_format($load[1], 2),
            '15min' => number_format($load[2], 2)
        ];
    }

    public static function getNetworkInfo()
    {
        $interfaces = [];
        $ifconfig = shell_exec('ifconfig -a');
        if (!$ifconfig)
            return ['Network Info' => 'Not available'];
        preg_match_all('/^(\S+): flags/m', $ifconfig, $matches);
        foreach ($matches[1] as $interface) {
            preg_match("/$interface:.*?inet (\d+\.\d+\.\d+\.\d+)/s", $ifconfig, $ip);
            preg_match("/$interface:.*?ether ([\da-f:]+)/s", $ifconfig, $mac);
            preg_match("/$interface:.*?RX packets.*?bytes (\d+)/s", $ifconfig, $rx);
            preg_match("/$interface:.*?TX packets.*?bytes (\d+)/s", $ifconfig, $tx);
            $interfaces[$interface] = [
                'IP Address' => $ip[1] ?? 'Not available',
                'MAC Address' => $mac[1] ?? 'Not available',
                'RX Data' => isset($rx[1]) ? self::formatBytes($rx[1]) : 'Not available',
                'TX Data' => isset($tx[1]) ? self::formatBytes($tx[1]) : 'Not available'
            ];
        }
        return $interfaces;
    }

    public static function getProcessList()
    {
        $processes = [];
        $ps = shell_exec('ps aux --sort=-%mem');
        if (!$ps)
            return ['Process List' => 'Not available'];
        $lines = explode("\n", $ps);
        array_shift($lines); // some versions of ps have a header
        foreach ($lines as $line) {
            if (trim($line) === '')
                continue;
            $columns = preg_split('/\s+/', $line, 11);
            if (count($columns) >= 11) {
                $processes[] = [
                    'User' => $columns[0],
                    'PID' => $columns[1],
                    'CPU' => $columns[2],
                    'Memory' => $columns[3],
                    'Command' => $columns[10]
                ];
            }
        }
        return $processes;
    }

    public static function getSwapInfo()
    {
        if (!file_exists('/proc/meminfo'))
            return ['Swap Info' => 'Not available'];
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/SwapFree:\s+(\d+)/', $meminfo, $free);
        $total = isset($total[1]) ? (int) $total[1] * 1024 : 0;
        $free = isset($free[1]) ? (int) $free[1] * 1024 : 0;
        $used = $total - $free;
        $usage = $total > 0 ? round(($used / $total) * 100, 2) . '%' : '0%';
        return [
            'Total' => self::formatBytes($total),
            'Used' => self::formatBytes($used),
            'Free' => self::formatBytes($free),
            'Usage' => $usage
        ];
    }

    public static function getUserInfo()
    {
        $users = [];
        $who = shell_exec('who');
        if (!$who)
            return ['User Info' => 'Not available'];
        foreach (explode("\n", $who) as $line) {
            if (trim($line) === '')
                continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 5) {
                $users[] = [
                    'User' => $parts[0],
                    'Terminal' => $parts[1],
                    'Host' => $parts[2],
                    'Login Time' => $parts[3] . ' ' . $parts[4]
                ];
            }
        }
        return $users;
    }

    public static function getSecurityInfo()
    {
        $firewallStatus = shell_exec('command -v ufw >/dev/null 2>&1 && sudo ufw status');
        return [
            'Firewall Status' => $firewallStatus ?: 'Not available'
        ];
    }

    public static function getLogInfo()
    {
        if (!file_exists('/var/log/syslog'))
            return ['Log Info' => 'Not available'];
        $logs = [];
        $logEntries = shell_exec('tail -n 100 /var/log/syslog');
        if (!$logEntries)
            return ['Log Info' => 'Not available'];
        foreach (explode("\n", $logEntries) as $line) {
            if (trim($line) === '')
                continue;
            $logs[] = $line;
        }
        return $logs;
    }

    public static function getTemperatureInfo()
    {
        $temperature = shell_exec('command -v sensors >/dev/null 2>&1 && sensors');
        return [
            'Temperature' => $temperature ?: 'Not available'
        ];
    }
}
?>