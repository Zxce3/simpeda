<?php

// PocketBase schema and the service account the app uses for its own database access.
// Everything here is idempotent: `php index.php migrate` is safe to re-run after upgrades.

// Note: build.php concatenates src/ and api.php runs first, so later files must only declare
// functions and classes (a top-level const here would not be defined yet).
function serviceEmail()
{
    return 'simpeda-service@simpeda.local';
}

function schemaDefinition()
{
    $text = fn($name, $required = false) => ['name' => $name, 'type' => 'text', 'required' => $required];
    $num = fn($name) => ['name' => $name, 'type' => 'number'];
    $bool = fn($name) => ['name' => $name, 'type' => 'bool'];
    $json = fn($name) => ['name' => $name, 'type' => 'json', 'maxSize' => 2000000];
    $rel = fn($name, $target) => ['name' => $name, 'type' => 'relation', 'collection' => $target, 'maxSelect' => 1, 'cascadeDelete' => true];

    // Order matters: relations point at collections defined earlier.
    return [
        'servers' => [
            'fields' => [
                $text('name', true), $text('token_hash', true), $text('country_code'), $text('note'),
                $bool('public'), $num('sort'), $num('last_seen'), $num('last_history'),
                $json('host'), $json('live'),
            ],
            'indexes' => ['CREATE UNIQUE INDEX idx_servers_token ON servers (token_hash)'],
        ],
        'metrics' => [
            'fields' => [
                $rel('server', 'servers'), $num('ts'), $num('cpu'), $num('mem'), $num('swap'), $num('disk'),
                $num('load1'), $num('load5'), $num('load15'), $num('rx_speed'), $num('tx_speed'),
                $num('rx_total'), $num('tx_total'), $num('uptime'), $num('procs'),
            ],
            'indexes' => ['CREATE INDEX idx_metrics_server_ts ON metrics (server, ts)', 'CREATE INDEX idx_metrics_ts ON metrics (ts)'],
        ],
        'monitors' => [
            'fields' => [
                $text('name', true), $text('type', true), $text('target', true), $num('interval'), $num('timeout'),
                $num('expect_status'), $bool('enabled'), $bool('public'), $num('last_run'), $num('last_ok'),
                $num('last_latency'), $num('fail_streak'),
            ],
        ],
        'monitor_results' => [
            'fields' => [
                $rel('monitor', 'monitors'), $num('ts'), $bool('ok'), $num('latency'), $num('status'), $text('error'),
            ],
            'indexes' => ['CREATE INDEX idx_results_monitor_ts ON monitor_results (monitor, ts)', 'CREATE INDEX idx_results_ts ON monitor_results (ts)'],
        ],
        'alert_events' => [
            'fields' => [$text('kind', true), $text('subject'), $text('message'), $num('ts'), $bool('resolved')],
            'indexes' => ['CREATE INDEX idx_alerts_ts ON alert_events (ts)'],
        ],
        'settings' => [
            'fields' => [$text('key', true), $json('value')],
            'indexes' => ['CREATE UNIQUE INDEX idx_settings_key ON settings (key)'],
        ],
    ];
}

function ensureCollection(PocketBaseClient $client, $name, array $definition)
{
    $existing = $client->get("/api/collections/$name");
    $fields = $definition['fields'];

    // Resolve relation targets (by name) to collection ids.
    foreach ($fields as &$field) {
        if (($field['type'] ?? '') === 'relation') {
            $target = $client->get('/api/collections/' . $field['collection']);
            $field['collectionId'] = $target['id'] ?? '';
            unset($field['collection']);
        }
    }
    unset($field);

    if ($client->lastStatus === 404 || empty($existing['id'])) {
        $created = $client->post('/api/collections', [
            'name' => $name,
            'type' => 'base',
            'fields' => $fields,
            'indexes' => $definition['indexes'] ?? [],
        ]);
        if (empty($created['id'])) {
            throw new RuntimeException("Could not create collection $name: " . json_encode($created));
        }
        return;
    }

    $have = array_column($existing['fields'], 'name');
    $missing = array_values(array_filter($fields, fn($f) => !in_array($f['name'], $have, true)));
    $indexes = array_values(array_unique(array_merge($existing['indexes'] ?? [], $definition['indexes'] ?? [])));
    if ($missing || count($indexes) !== count($existing['indexes'] ?? [])) {
        $client->patch("/api/collections/$name", [
            'fields' => array_merge($existing['fields'], $missing),
            'indexes' => $indexes,
        ]);
    }
}

function serviceCredentialsFile()
{
    return pocketbasePath() . '/service.json';
}

function readServiceCredentials()
{
    $data = @json_decode((string) @file_get_contents(serviceCredentialsFile()), true);
    return is_array($data) && !empty($data['email']) && !empty($data['password']) ? $data : null;
}

// Creates (or resets) the superuser the app uses to read/write its own collections.
function ensureServiceAccount(PocketBaseClient $admin)
{
    $creds = readServiceCredentials();
    if ($creds) {
        $probe = new PocketBaseClient(Auth::pocketbaseUrl());
        try {
            $probe->authWithPassword($creds['email'], $creds['password']);
            return;
        } catch (Exception $e) {
            // fall through and reset the password
        }
    }

    $password = bin2hex(random_bytes(24));
    $found = $admin->listRecords('_superusers', ['filter' => 'email="' . serviceEmail() . '"', 'perPage' => 1]);
    if (!empty($found['items'][0]['id'])) {
        $admin->patch('/api/collections/_superusers/records/' . $found['items'][0]['id'], [
            'password' => $password, 'passwordConfirm' => $password,
        ]);
    } else {
        $created = $admin->post('/api/collections/_superusers/records', [
            'email' => serviceEmail(), 'password' => $password, 'passwordConfirm' => $password,
        ]);
        if (empty($created['id'])) {
            throw new RuntimeException('Could not create the service account: ' . json_encode($created));
        }
    }

    $file = serviceCredentialsFile();
    $json = json_encode(['email' => serviceEmail(), 'password' => $password]);
    $old = umask(0177);
    $written = file_put_contents($file, $json);
    umask($old);
    if ($written === false) {
        throw new RuntimeException("Could not write $file");
    }
    chmod($file, 0600);
}

// $admin must be authenticated as a superuser.
function migrateSchema(PocketBaseClient $admin)
{
    foreach (schemaDefinition() as $name => $definition) {
        ensureCollection($admin, $name, $definition);
    }
    ensureServiceAccount($admin);
}
