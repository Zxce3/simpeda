<?php

// JSON API under /api/*. Agents authenticate with their token, /api/public is open (when enabled),
// everything else needs a logged-in session and, for writes, the CSRF header.

function onlineWindow()
{
    return 45; // seconds without a report before a server is shown as offline
}

function apiBody()
{
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function apiCsrfValid()
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return $token !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function serverView(array $row, $private = true)
{
    $now = time();
    $live = is_array($row['live'] ?? null) ? $row['live'] : [];
    $host = is_array($row['host'] ?? null) ? $row['host'] : [];
    $view = [
        'id' => $row['id'],
        'name' => $row['name'],
        'country_code' => $row['country_code'] ?? '',
        'online' => (int) ($row['last_seen'] ?? 0) > 0 && $now - (int) $row['last_seen'] <= onlineWindow(),
        'last_seen' => (int) ($row['last_seen'] ?? 0),
        'live' => $live,
        'host' => $private ? $host : ['platform' => $host['platform'] ?? '', 'arch' => $host['arch'] ?? ''],
    ];
    if ($private) {
        $view += ['note' => $row['note'] ?? '', 'public' => (bool) ($row['public'] ?? false), 'sort' => (int) ($row['sort'] ?? 0)];
    } else {
        $view['live'] = array_intersect_key($live, array_flip(['cpu', 'mem', 'disk', 'load1', 'rx_speed', 'tx_speed', 'uptime']));
    }
    return $view;
}

function listServersView($private = true)
{
    $filter = $private ? '' : 'public=true';
    $params = ['sort' => 'sort,name'] + ($filter !== '' ? ['filter' => $filter] : []);
    return array_map(fn($r) => serverView($r, $private), Store::list('servers', $params));
}

function rangeSeconds($range)
{
    return ['1h' => 3600, '6h' => 21600, '24h' => 86400, '7d' => 604800][$range] ?? 3600;
}

function serverHistory($id, $range)
{
    $to = time();
    $from = $to - rangeSeconds($range);
    $rows = Store::all(
        'metrics',
        'server=' . Store::quote($id) . ' && ts>=' . $from,
        'ts',
        'ts,cpu,mem,swap,disk,load1,rx_speed,tx_speed'
    );
    $fields = ['cpu', 'mem', 'swap', 'disk', 'load1', 'rx_speed', 'tx_speed'];
    $buckets = 240;
    $size = max(($to - $from) / $buckets, 1);
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) floor(($row['ts'] - $from) / $size)][] = $row;
    }
    $points = [];
    foreach ($grouped as $group) {
        $point = ['ts' => (int) round(array_sum(array_column($group, 'ts')) / count($group))];
        foreach ($fields as $f) {
            $point[$f] = round(array_sum(array_column($group, $f)) / count($group), 2);
        }
        $points[] = $point;
    }
    usort($points, fn($a, $b) => $a['ts'] <=> $b['ts']);
    return ['from' => $from, 'to' => $to, 'points' => $points];
}

function monitorView(array $m, $withResults = true)
{
    $view = [
        'id' => $m['id'], 'name' => $m['name'], 'type' => $m['type'], 'target' => $m['target'],
        'interval' => (int) $m['interval'], 'timeout' => (int) $m['timeout'], 'expect_status' => (int) $m['expect_status'],
        'enabled' => (bool) $m['enabled'], 'public' => (bool) $m['public'],
        'last_run' => (int) $m['last_run'], 'last_ok' => (bool) $m['last_ok'], 'last_latency' => (float) $m['last_latency'],
        'fail_streak' => (int) $m['fail_streak'],
    ];
    if ($withResults) {
        $rows = Store::all('monitor_results', 'monitor=' . Store::quote($m['id']) . ' && ts>=' . (time() - 86400), 'ts', 'ts,ok,latency');
        $ok = count(array_filter($rows, fn($r) => $r['ok']));
        $view['uptime24'] = $rows ? round(100 * $ok / count($rows), 2) : null;
        $view['recent'] = array_map(fn($r) => ['ts' => (int) $r['ts'], 'ok' => (bool) $r['ok'], 'latency' => (float) $r['latency']], array_slice($rows, -60));
    }
    return $view;
}

function maskSecret($value)
{
    return $value ? '********' : '';
}

function settingsView()
{
    $notify = Store::setting('notify', []);
    $notify = is_array($notify) ? $notify : [];
    return [
        'site_title' => Store::setting('site_title', 'Server Status'),
        'public_status' => (bool) Store::setting('public_status', false),
        'alerts' => alertSettings(),
        'notify' => [
            'type' => $notify['type'] ?? '', 'url' => maskSecret($notify['url'] ?? ''),
            'token' => maskSecret($notify['token'] ?? ''), 'chat_id' => $notify['chat_id'] ?? '',
        ],
        'retention_days' => retentionDays(),
    ];
}

function saveSettings(array $in)
{
    if (isset($in['site_title'])) {
        Store::setSetting('site_title', mb_substr(trim((string) $in['site_title']), 0, 80) ?: 'Server Status');
    }
    if (isset($in['public_status'])) {
        Store::setSetting('public_status', (bool) $in['public_status']);
    }
    if (isset($in['alerts']) && is_array($in['alerts'])) {
        $limits = ['offline_after' => [15, 86400], 'cpu' => [0, 100], 'mem' => [0, 100], 'disk' => [0, 100], 'sustain' => [0, 3600], 'monitor_fails' => [1, 100]];
        $clean = alertSettings();
        foreach ($limits as $key => [$min, $max]) {
            if (isset($in['alerts'][$key]) && is_numeric($in['alerts'][$key])) {
                $clean[$key] = min(max((float) $in['alerts'][$key], $min), $max);
            }
        }
        Store::setSetting('alerts', $clean);
    }
    if (isset($in['notify']) && is_array($in['notify'])) {
        $old = Store::setting('notify', []);
        $old = is_array($old) ? $old : [];
        $type = (string) ($in['notify']['type'] ?? '');
        if (!in_array($type, ['', 'webhook', 'discord', 'telegram'], true)) {
            throw new InvalidArgumentException('Unknown notification type');
        }
        $keep = fn($key) => ($in['notify'][$key] ?? '') === '********' ? ($old[$key] ?? '') : trim((string) ($in['notify'][$key] ?? ''));
        $notify = ['type' => $type, 'url' => $keep('url'), 'token' => $keep('token'), 'chat_id' => trim((string) ($in['notify']['chat_id'] ?? ''))];
        if ($notify['url'] !== '' && !preg_match('#^https?://#i', $notify['url'])) {
            throw new InvalidArgumentException('The notification URL must start with http:// or https://');
        }
        Store::setSetting('notify', $notify);
    }
}

function validateServerInput(array $in, array $current = [])
{
    $out = [];
    if (array_key_exists('name', $in) || !$current) {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 64) {
            throw new InvalidArgumentException('Name is required (max 64 characters)');
        }
        $out['name'] = $name;
    }
    if (array_key_exists('country_code', $in)) {
        $cc = strtoupper(trim((string) $in['country_code']));
        if ($cc !== '' && !preg_match('/^[A-Z]{2}$/', $cc)) {
            throw new InvalidArgumentException('Country code must be two letters, e.g. DE');
        }
        $out['country_code'] = $cc;
    }
    if (array_key_exists('note', $in)) {
        $out['note'] = mb_substr(trim((string) $in['note']), 0, 200);
    }
    if (array_key_exists('public', $in)) {
        $out['public'] = (bool) $in['public'];
    }
    if (array_key_exists('sort', $in)) {
        $out['sort'] = (int) $in['sort'];
    }
    return $out;
}

function validateMonitorInput(array $in, array $current = [])
{
    $merged = array_merge($current, $in);
    $name = trim((string) ($merged['name'] ?? ''));
    $type = (string) ($merged['type'] ?? '');
    $target = trim((string) ($merged['target'] ?? ''));
    if ($name === '' || mb_strlen($name) > 64) {
        throw new InvalidArgumentException('Name is required (max 64 characters)');
    }
    if (!in_array($type, ['http', 'tcp', 'ping'], true)) {
        throw new InvalidArgumentException('Type must be http, tcp or ping');
    }
    if (!validMonitorTarget($type, $target)) {
        throw new InvalidArgumentException($type === 'http' ? 'Target must be an http(s) URL' : ($type === 'tcp' ? 'Target must be host:port' : 'Target must be a hostname or IP'));
    }
    $int = fn($key, $default, $min, $max) => min(max((int) ($merged[$key] ?? $default), $min), $max);
    return [
        'name' => $name, 'type' => $type, 'target' => $target,
        'interval' => $int('interval', 60, 10, 86400), 'timeout' => $int('timeout', 10, 1, 60),
        'expect_status' => $int('expect_status', 0, 0, 599),
        'enabled' => (bool) ($merged['enabled'] ?? true), 'public' => (bool) ($merged['public'] ?? false),
    ];
}

function publicStatus()
{
    if (!Store::setting('public_status', false)) {
        return null;
    }
    $monitors = [];
    foreach (Store::list('monitors', ['filter' => 'public=true && enabled=true', 'sort' => 'name']) as $m) {
        $v = monitorView($m);
        $monitors[] = ['name' => $v['name'], 'ok' => $v['last_ok'], 'latency' => $v['last_latency'], 'uptime24' => $v['uptime24'], 'recent' => $v['recent'], 'checked' => $v['last_run'] > 0];
    }
    return [
        'title' => Store::setting('site_title', 'Server Status'),
        'servers' => listServersView(false),
        'monitors' => $monitors,
        'time' => time(),
    ];
}

// Returns true when the request was an API call (and a response was sent).
function handleApi($method, $path)
{
    if (strncmp($path, '/api/', 5) !== 0) {
        return false;
    }
    $segments = array_values(array_filter(explode('/', substr($path, 5)), 'strlen'));
    $resource = $segments[0] ?? '';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;
    header('Cache-Control: no-store');

    try {
        if (!readServiceCredentials()) {
            sendResponse(['error' => 'Service account missing. Run: php index.php migrate'], 503);
            return true;
        }

        if ($resource === 'agent' && $id === 'report' && $method === 'POST') {
            handleAgentReport();
            return true;
        }
        if ($resource === 'public' && $method === 'GET') {
            $status = publicStatus();
            $status === null ? sendResponse(['error' => 'Not found'], 404) : sendResponse($status);
            return true;
        }

        if (!isUserAuthenticated()) {
            sendResponse(['error' => 'Unauthorized'], 401);
            return true;
        }
        if ($method !== 'GET' && !apiCsrfValid()) {
            sendResponse(['error' => 'Invalid CSRF token'], 403);
            return true;
        }
        if ($method === 'GET' && $resource === 'session') {
            sendResponse(['csrf' => csrfToken(), 'version' => appVersion()]);
            return true;
        }
        $body = $method === 'GET' ? [] : apiBody();

        switch ("$method $resource") {
            case 'GET servers':
                if ($id && $action === 'history') {
                    sendResponse(serverHistory($id, $_GET['range'] ?? '1h'));
                } elseif ($id) {
                    $row = Store::get('servers', $id);
                    $row ? sendResponse(serverView($row)) : sendResponse(['error' => 'Not found'], 404);
                } else {
                    sendResponse(['servers' => listServersView(), 'time' => time()]);
                }
                break;
            case 'POST servers':
                if ($id && $action === 'token') {
                    $token = 'spk_' . bin2hex(random_bytes(24));
                    Store::update('servers', $id, ['token_hash' => hash('sha256', $token)]);
                    sendResponse(['id' => $id, 'token' => $token]);
                    break;
                }
                $data = validateServerInput($body);
                $token = 'spk_' . bin2hex(random_bytes(24));
                $row = Store::create('servers', $data + ['token_hash' => hash('sha256', $token), 'public' => false, 'sort' => 0, 'last_seen' => 0, 'last_history' => 0]);
                sendResponse(['id' => $row['id'], 'token' => $token], 201);
                break;
            case 'PATCH servers':
                $current = Store::get('servers', (string) $id);
                if (!$current) {
                    sendResponse(['error' => 'Not found'], 404);
                    break;
                }
                Store::update('servers', $id, validateServerInput($body, $current));
                sendResponse(['ok' => true]);
                break;
            case 'DELETE servers':
                Store::delete('servers', (string) $id);
                sendResponse(['ok' => true]);
                break;

            case 'GET monitors':
                if ($id && $action === 'results') {
                    $seconds = rangeSeconds($_GET['range'] ?? '1h');
                    $rows = Store::all('monitor_results', 'monitor=' . Store::quote($id) . ' && ts>=' . (time() - $seconds), 'ts', 'ts,ok,latency,status,error');
                    sendResponse(['results' => array_slice($rows, -500)]);
                } else {
                    sendResponse(['monitors' => array_map('monitorView', Store::list('monitors', ['sort' => 'name']))]);
                }
                break;
            case 'POST monitors':
                $data = validateMonitorInput($body);
                $row = Store::create('monitors', $data + ['last_run' => 0, 'last_ok' => 0, 'last_latency' => 0, 'fail_streak' => 0]);
                sendResponse(['id' => $row['id']], 201);
                break;
            case 'PATCH monitors':
                $current = Store::get('monitors', (string) $id);
                if (!$current) {
                    sendResponse(['error' => 'Not found'], 404);
                    break;
                }
                Store::update('monitors', $id, validateMonitorInput($body, $current));
                sendResponse(['ok' => true]);
                break;
            case 'DELETE monitors':
                Store::delete('monitors', (string) $id);
                sendResponse(['ok' => true]);
                break;

            case 'GET settings':
                sendResponse(settingsView());
                break;
            case 'PUT settings':
            case 'PATCH settings':
                saveSettings($body);
                sendResponse(settingsView());
                break;
            case 'POST settings':
                if ($id === 'test') {
                    $sent = sendNotification('SIMPEDA test', 'If you can read this, notifications work.');
                    sendResponse(['sent' => $sent], $sent ? 200 : 502);
                    break;
                }
                sendResponse(['error' => 'Not found'], 404);
                break;

            case 'GET alerts':
                sendResponse(['alerts' => Store::list('alert_events', ['sort' => '-ts', 'perPage' => 50])]);
                break;

            default:
                sendResponse(['error' => 'Not found'], 404);
        }
    } catch (InvalidArgumentException $e) {
        sendResponse(['error' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log('API error: ' . $e->getMessage());
        sendResponse(['error' => 'Internal Server Error'], 500);
    }
    return true;
}
