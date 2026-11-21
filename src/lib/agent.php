<?php

// Metrics collection (Linux /proc), the agent loop (`php index.php agent`) and the ingest endpoint.

function appVersion()
{
    return defined('SIMPEDA_VERSION') ? SIMPEDA_VERSION : 'dev';
}

function readCpuTimes()
{
    $line = @file('/proc/stat', FILE_IGNORE_NEW_LINES)[0] ?? '';
    $parts = preg_split('/\s+/', trim($line));
    if (($parts[0] ?? '') !== 'cpu' || count($parts) < 5) {
        return null;
    }
    $values = array_map('intval', array_slice($parts, 1, 8));
    $idle = $values[3] + ($values[4] ?? 0);
    return ['total' => array_sum($values), 'idle' => $idle];
}

function readNetTotals()
{
    $rx = $tx = 0;
    foreach (@file('/proc/net/dev', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$name, $data] = explode(':', $line, 2);
        $name = trim($name);
        if ($name === 'lo' || preg_match('/^(veth|docker|br-|virbr|cni|flannel|tun|tap)/', $name)) {
            continue;
        }
        $cols = preg_split('/\s+/', trim($data));
        $rx += (int) ($cols[0] ?? 0);
        $tx += (int) ($cols[8] ?? 0);
    }
    return [$rx, $tx];
}

function readMeminfo()
{
    $info = [];
    foreach (@file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
            $info[$m[1]] = (int) $m[2] * 1024;
        }
    }
    return $info;
}

// $prev keeps the previous CPU/network counters between calls so rates can be computed.
function collectMetrics(array &$prev)
{
    $cpu = readCpuTimes();
    [$rx, $tx] = readNetTotals();
    $now = microtime(true);
    if (!isset($prev['cpu'])) {
        // First sample: wait briefly so there is something to diff against.
        $prev = ['cpu' => $cpu, 'rx' => $rx, 'tx' => $tx, 'at' => $now];
        usleep(500000);
        $cpu = readCpuTimes();
        [$rx, $tx] = readNetTotals();
        $now = microtime(true);
    }

    $cpuPercent = 0.0;
    if ($cpu && $prev['cpu'] && $cpu['total'] > $prev['cpu']['total']) {
        $cpuPercent = 100 * (1 - ($cpu['idle'] - $prev['cpu']['idle']) / ($cpu['total'] - $prev['cpu']['total']));
    }
    $dt = max($now - $prev['at'], 0.001);
    $rxSpeed = $rx >= $prev['rx'] ? ($rx - $prev['rx']) / $dt : 0;
    $txSpeed = $tx >= $prev['tx'] ? ($tx - $prev['tx']) / $dt : 0;
    $prev = ['cpu' => $cpu, 'rx' => $rx, 'tx' => $tx, 'at' => $now];

    $mem = readMeminfo();
    $memTotal = $mem['MemTotal'] ?? 0;
    $memUsed = max($memTotal - ($mem['MemAvailable'] ?? 0), 0);
    $swapTotal = $mem['SwapTotal'] ?? 0;
    $swapUsed = max($swapTotal - ($mem['SwapFree'] ?? 0), 0);
    $diskTotal = (float) @disk_total_space('/');
    $diskUsed = max($diskTotal - (float) @disk_free_space('/'), 0);
    $load = sys_getloadavg() ?: [0, 0, 0];
    $uptime = (int) @file_get_contents('/proc/uptime');

    return [
        'cpu' => round($cpuPercent, 2),
        'mem' => $memTotal ? round(100 * $memUsed / $memTotal, 2) : 0,
        'mem_used' => $memUsed, 'mem_total' => $memTotal,
        'swap' => $swapTotal ? round(100 * $swapUsed / $swapTotal, 2) : 0,
        'swap_used' => $swapUsed, 'swap_total' => $swapTotal,
        'disk' => $diskTotal ? round(100 * $diskUsed / $diskTotal, 2) : 0,
        'disk_used' => $diskUsed, 'disk_total' => $diskTotal,
        'load1' => round($load[0], 2), 'load5' => round($load[1], 2), 'load15' => round($load[2], 2),
        'rx_speed' => round($rxSpeed), 'tx_speed' => round($txSpeed),
        'rx_total' => $rx, 'tx_total' => $tx,
        'uptime' => $uptime,
        'procs' => count(@glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: []),
    ];
}

function collectHost()
{
    $os = '';
    foreach (@file('/etc/os-release', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (strpos($line, 'PRETTY_NAME=') === 0) {
            $os = trim(substr($line, 12), '"');
        }
    }
    $cpuinfo = (string) @file_get_contents('/proc/cpuinfo');
    preg_match('/model name\s*:\s*(.+)$/m', $cpuinfo, $model);
    $mem = readMeminfo();
    return [
        'hostname' => gethostname(),
        'platform' => $os ?: php_uname('s'),
        'kernel' => php_uname('r'),
        'arch' => php_uname('m'),
        'cpu_model' => trim($model[1] ?? ''),
        'cores' => max(preg_match_all('/^processor\s*:/m', $cpuinfo), 1),
        'mem_total' => $mem['MemTotal'] ?? 0,
        'swap_total' => $mem['SwapTotal'] ?? 0,
        'disk_total' => (float) @disk_total_space('/'),
        'boot_time' => time() - (int) @file_get_contents('/proc/uptime'),
        'agent' => appVersion(),
    ];
}

function postJson($url, array $data, array $headers = [], $timeout = 10)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $body = curl_exec($ch);
    $error = curl_errno($ch) ? curl_error($ch) : '';
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body === false ? '' : $body, 'error' => $error];
}

// php index.php agent --url=https://dash.example.com --token=TOKEN [--interval=5] [--once]
function runAgent(array $args)
{
    $url = rtrim($args['url'] ?? (getenv('SIMPEDA_URL') ?: ''), '/');
    $token = $args['token'] ?? (getenv('SIMPEDA_AGENT_TOKEN') ?: '');
    if (!preg_match('#^https?://#', $url) || $token === '') {
        fwrite(STDERR, "Usage: php index.php agent --url=https://dashboard.example.com --token=TOKEN [--interval=5] [--once]\n"
            . "(or set SIMPEDA_URL, SIMPEDA_AGENT_TOKEN and SIMPEDA_INTERVAL)\n");
        return 1;
    }
    $interval = max((int) ($args['interval'] ?? (getenv('SIMPEDA_INTERVAL') ?: 5)), 1);
    $once = isset($args['once']);
    installSignalHandlers();

    $prev = [];
    $lastHost = 0;
    $backoff = 0;
    while (true) {
        $payload = ['metrics' => collectMetrics($prev)];
        if (time() - $lastHost >= 600) {
            $payload['host'] = collectHost();
        }
        $res = postJson("$url/api/agent/report", $payload, ["Authorization: Bearer $token"]);
        if ($res['status'] === 200) {
            if (isset($payload['host'])) {
                $lastHost = time();
            }
            $backoff = 0;
            if ($once) {
                echo "Reported OK\n";
                return 0;
            }
        } else {
            $why = $res['error'] ?: "HTTP {$res['status']} {$res['body']}";
            fwrite(STDERR, '[' . date('c') . "] report failed: $why\n");
            if ($res['status'] === 401 || $once) {
                return 1;
            }
            $backoff = min(max($backoff * 2, $interval), 60);
        }
        sleep($backoff ?: $interval);
    }
}

function installSignalHandlers()
{
    if (function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, static function () {
                exit(0);
            });
        }
    }
}

// The installer script is embedded here by build.php (src/agent-install.sh).
function agentInstallScript()
{
    return '__AGENT_INSTALL_SH__';
}

// --- ingest -----------------------------------------------------------------------------

function bearerToken()
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }
    return trim($_SERVER['HTTP_X_AGENT_TOKEN'] ?? '');
}

function sanitizeMetrics($input)
{
    $keys = ['cpu', 'mem', 'mem_used', 'mem_total', 'swap', 'swap_used', 'swap_total', 'disk', 'disk_used', 'disk_total',
        'load1', 'load5', 'load15', 'rx_speed', 'tx_speed', 'rx_total', 'tx_total', 'uptime', 'procs'];
    $out = [];
    foreach ($keys as $key) {
        $value = is_array($input) && isset($input[$key]) && is_numeric($input[$key]) ? (float) $input[$key] : 0;
        $out[$key] = is_finite($value) ? $value : 0;
    }
    foreach (['cpu', 'mem', 'swap', 'disk'] as $percent) {
        $out[$percent] = min(max($out[$percent], 0), 100);
    }
    return $out;
}

function sanitizeHost($input)
{
    if (!is_array($input)) {
        return null;
    }
    $text = fn($key, $max = 120) => mb_substr(is_scalar($input[$key] ?? null) ? (string) $input[$key] : '', 0, $max);
    $num = fn($key) => is_numeric($input[$key] ?? null) ? (float) $input[$key] : 0;
    return [
        'hostname' => $text('hostname'), 'platform' => $text('platform'), 'kernel' => $text('kernel'),
        'arch' => $text('arch', 20), 'cpu_model' => $text('cpu_model'), 'agent' => $text('agent', 40),
        'cores' => $num('cores'), 'mem_total' => $num('mem_total'), 'swap_total' => $num('swap_total'),
        'disk_total' => $num('disk_total'), 'boot_time' => $num('boot_time'),
    ];
}

function historyInterval()
{
    return 60;
}

function handleAgentReport()
{
    $token = bearerToken();
    if ($token === '') {
        return sendResponse(['error' => 'Missing token'], 401);
    }
    $server = Store::find('servers', 'token_hash=' . Store::quote(hash('sha256', $token)));
    if (!$server) {
        return sendResponse(['error' => 'Unknown token'], 401);
    }

    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $body = json_decode((string) $raw, true);
    if (!is_array($body) || !isset($body['metrics'])) {
        return sendResponse(['error' => 'Invalid payload'], 400);
    }

    $now = time();
    $metrics = sanitizeMetrics($body['metrics']);
    $update = ['last_seen' => $now, 'live' => $metrics + ['ts' => $now]];
    if (($host = sanitizeHost($body['host'] ?? null)) !== null) {
        $update['host'] = $host;
    }
    if ($now - (int) ($server['last_history'] ?? 0) >= historyInterval()) {
        Store::create('metrics', $metrics + ['server' => $server['id'], 'ts' => $now]);
        $update['last_history'] = $now;
    }
    Store::update('servers', $server['id'], $update);
    return sendResponse(['ok' => true]);
}
