<?php

// Background worker (`php index.php worker`): service monitors, alerts and data pruning.

function defaultAlertSettings()
{
    return [
        'offline_after' => 60,     // seconds without a report before a server counts as offline
        'cpu' => 90, 'mem' => 90, 'disk' => 90,   // percent, 0 disables
        'sustain' => 120,          // seconds a CPU/memory breach must last before alerting
        'monitor_fails' => 3,      // consecutive failed checks before a monitor is "down"
    ];
}

function alertSettings()
{
    $stored = Store::setting('alerts', []);
    return array_merge(defaultAlertSettings(), is_array($stored) ? array_intersect_key($stored, defaultAlertSettings()) : []);
}

function retentionDays()
{
    $days = (int) (getenv('SIMPEDA_RETENTION_DAYS') ?: 7);
    return min(max($days, 1), 90);
}

// --- monitors ---------------------------------------------------------------------------

function validMonitorTarget($type, $target)
{
    if ($type === 'http') {
        return (bool) preg_match('#^https?://[^\s/]+#i', $target) && filter_var($target, FILTER_VALIDATE_URL) !== false;
    }
    if ($type === 'tcp') {
        return (bool) preg_match('/^[A-Za-z0-9._-]+:\d{1,5}$/', $target);
    }
    if ($type === 'ping') {
        return (bool) preg_match('/^[A-Za-z0-9._-]+$/', $target);
    }
    return false;
}

// Returns ['ok' => bool, 'latency' => ms, 'status' => int, 'error' => string].
function runMonitorCheck(array $monitor)
{
    $timeout = min(max((int) ($monitor['timeout'] ?? 10), 1), 60);
    $target = (string) $monitor['target'];
    $result = ['ok' => false, 'latency' => 0, 'status' => 0, 'error' => ''];

    switch ($monitor['type']) {
        case 'http':
            $ch = curl_init($target);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_USERAGENT => 'simpeda-monitor/' . appVersion(),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            curl_exec($ch);
            $result['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['latency'] = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
            if (curl_errno($ch)) {
                $result['error'] = curl_error($ch);
            } else {
                $expect = (int) ($monitor['expect_status'] ?? 0);
                $result['ok'] = $expect > 0 ? $result['status'] === $expect : ($result['status'] >= 200 && $result['status'] < 400);
                if (!$result['ok']) {
                    $result['error'] = 'Unexpected status ' . $result['status'];
                }
            }
            curl_close($ch);
            break;

        case 'tcp':
            [$host, $port] = explode(':', $target);
            $start = microtime(true);
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, $timeout);
            $result['latency'] = round((microtime(true) - $start) * 1000);
            if ($socket) {
                fclose($socket);
                $result['ok'] = true;
            } else {
                $result['error'] = $errstr ?: 'Connection failed';
            }
            break;

        case 'ping':
            $output = [];
            $code = 1;
            @exec(sprintf('ping -c 1 -W %d %s 2>&1', $timeout, escapeshellarg($target)), $output, $code);
            $text = implode("\n", $output);
            if ($code === 0 && preg_match('/time[=<]([\d.]+)\s*ms/', $text, $m)) {
                $result['ok'] = true;
                $result['latency'] = round((float) $m[1], 2);
            } else {
                $result['error'] = $code === 127 ? 'ping is not installed' : 'No reply';
            }
            break;

        default:
            $result['error'] = 'Unknown monitor type';
    }
    return $result;
}

function runDueMonitors()
{
    $now = time();
    foreach (Store::list('monitors', ['filter' => 'enabled=true']) as $monitor) {
        $interval = max((int) $monitor['interval'], 10);
        if ($now - (int) $monitor['last_run'] < $interval) {
            continue;
        }
        $result = runMonitorCheck($monitor);
        Store::create('monitor_results', [
            'monitor' => $monitor['id'], 'ts' => $now, 'ok' => $result['ok'],
            'latency' => $result['latency'], 'status' => $result['status'], 'error' => mb_substr($result['error'], 0, 200),
        ]);
        Store::update('monitors', $monitor['id'], [
            'last_run' => $now, 'last_ok' => $result['ok'] ? 1 : 0, 'last_latency' => $result['latency'],
            'fail_streak' => $result['ok'] ? 0 : (int) $monitor['fail_streak'] + 1,
        ]);
    }
}

// --- alerts -----------------------------------------------------------------------------

function sendNotification($title, $text)
{
    $notify = Store::setting('notify', []);
    $type = $notify['type'] ?? '';
    $message = "$title\n$text";
    if ($type === 'discord' && !empty($notify['url'])) {
        return postJson($notify['url'], ['content' => $message])['status'] < 300;
    }
    if ($type === 'telegram' && !empty($notify['token']) && !empty($notify['chat_id'])) {
        $url = 'https://api.telegram.org/bot' . rawurlencode($notify['token']) . '/sendMessage';
        return postJson($url, ['chat_id' => $notify['chat_id'], 'text' => $message])['status'] < 300;
    }
    if ($type === 'webhook' && !empty($notify['url'])) {
        return postJson($notify['url'], ['title' => $title, 'text' => $text, 'message' => $message, 'time' => date('c')])['status'] < 300;
    }
    return false;
}

function raiseAlert($kind, $subject, $message)
{
    $open = Store::find('alert_events', 'kind=' . Store::quote($kind) . ' && subject=' . Store::quote($subject) . ' && resolved=false');
    if ($open) {
        return;
    }
    Store::create('alert_events', ['kind' => $kind, 'subject' => $subject, 'message' => $message, 'ts' => time(), 'resolved' => false]);
    sendNotification("[ALERT] $subject", $message);
}

function resolveAlert($kind, $subject, $message)
{
    $open = Store::find('alert_events', 'kind=' . Store::quote($kind) . ' && subject=' . Store::quote($subject) . ' && resolved=false');
    if (!$open) {
        return;
    }
    Store::update('alert_events', $open['id'], ['resolved' => true]);
    sendNotification("[OK] $subject", $message);
}

// $breach keeps first-breach timestamps between runs so only sustained load alerts.
function evaluateAlerts(array &$breach)
{
    $cfg = alertSettings();
    $now = time();

    foreach (Store::list('servers') as $server) {
        $name = $server['name'];
        $seen = (int) $server['last_seen'];
        if ($seen === 0) {
            continue; // never reported yet
        }
        if ($now - $seen > $cfg['offline_after']) {
            raiseAlert('offline', $name, "$name has not reported for " . ($now - $seen) . 's');
            continue;
        }
        resolveAlert('offline', $name, "$name is reporting again");

        $live = is_array($server['live']) ? $server['live'] : [];
        foreach (['cpu' => 'CPU', 'mem' => 'Memory', 'disk' => 'Disk'] as $key => $label) {
            $limit = (float) $cfg[$key];
            $value = (float) ($live[$key] ?? 0);
            $slot = $server['id'] . ':' . $key;
            $subject = "$name $label";
            if ($limit > 0 && $value >= $limit) {
                $breach[$slot] = $breach[$slot] ?? $now;
                $needed = $key === 'disk' ? 0 : (int) $cfg['sustain'];
                if ($now - $breach[$slot] >= $needed) {
                    raiseAlert($key, $subject, sprintf('%s is at %.1f%% (limit %d%%)', $subject, $value, $limit));
                }
            } else {
                unset($breach[$slot]);
                resolveAlert($key, $subject, "$subject is back to normal ({$value}%)");
            }
        }
    }

    foreach (Store::list('monitors', ['filter' => 'enabled=true']) as $monitor) {
        if ((int) $monitor['fail_streak'] >= max((int) $cfg['monitor_fails'], 1)) {
            raiseAlert('monitor', $monitor['name'], "Monitor {$monitor['name']} ({$monitor['target']}) is down");
        } elseif ((int) $monitor['last_run'] > 0 && (int) $monitor['fail_streak'] === 0) {
            resolveAlert('monitor', $monitor['name'], "Monitor {$monitor['name']} is up again");
        }
    }
}

// --- housekeeping -----------------------------------------------------------------------

function pruneOldData()
{
    $cutoff = time() - retentionDays() * 86400;
    $deleted = 0;
    foreach (['metrics', 'monitor_results', 'alert_events'] as $collection) {
        $filter = 'ts<' . $cutoff . ($collection === 'alert_events' ? ' && resolved=true' : '');
        foreach (Store::list($collection, ['filter' => $filter, 'perPage' => 500, 'fields' => 'id']) as $row) {
            Store::delete($collection, $row['id']);
            if (++$deleted >= 3000) {
                return $deleted; // continue on the next run
            }
        }
    }
    return $deleted;
}

// php index.php worker
function runWorker()
{
    if (!readServiceCredentials()) {
        fwrite(STDERR, "Service account missing. Run: php index.php migrate\n");
        return 1;
    }
    installSignalHandlers();
    $breach = [];
    $lastAlerts = $lastPrune = 0;
    echo "Worker started (retention " . retentionDays() . " days)\n";
    while (true) {
        $now = time();
        try {
            runDueMonitors();
            if ($now - $lastAlerts >= 10) {
                evaluateAlerts($breach);
                $lastAlerts = $now;
            }
            if ($now - $lastPrune >= 3600) {
                pruneOldData();
                $lastPrune = $now;
            }
        } catch (Throwable $e) {
            fwrite(STDERR, '[' . date('c') . '] worker error: ' . $e->getMessage() . "\n");
            sleep(5);
        }
        sleep(2);
    }
}
