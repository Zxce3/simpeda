<?php

// Data access for the app's own collections, using the service account (see schema.php).
class Store
{
    private static $client = null;

    private static function client()
    {
        if (self::$client === null) {
            $creds = readServiceCredentials();
            if (!$creds) {
                throw new RuntimeException('Service account missing. Run: php index.php migrate');
            }
            $client = new PocketBaseClient(Auth::pocketbaseUrl());
            $client->authWithPassword($creds['email'], $creds['password']);
            self::$client = $client;
        }
        return self::$client;
    }

    // Runs $fn($client), re-authenticating once if the service token expired.
    private static function call(callable $fn)
    {
        $result = $fn(self::client());
        if (self::$client->lastStatus === 401) {
            self::$client = null;
            $result = $fn(self::client());
        }
        $status = self::$client->lastStatus;
        if ($status >= 400) {
            $message = is_array($result) ? ($result['message'] ?? '') : '';
            throw new RuntimeException("PocketBase error $status: $message");
        }
        return $result;
    }

    public static function list($collection, array $params = [])
    {
        $params += ['perPage' => 200, 'skipTotal' => 1];
        return self::call(fn($c) => $c->listRecords($collection, $params))['items'] ?? [];
    }

    // Walks every page; use a $filter that bounds the result.
    public static function all($collection, $filter = '', $sort = '', $fields = '')
    {
        $items = [];
        for ($page = 1; $page <= 200; $page++) {
            $params = ['page' => $page, 'perPage' => 500, 'skipTotal' => 1];
            foreach (['filter' => $filter, 'sort' => $sort, 'fields' => $fields] as $key => $value) {
                if ($value !== '') {
                    $params[$key] = $value;
                }
            }
            $batch = self::call(fn($c) => $c->listRecords($collection, $params))['items'] ?? [];
            $items = array_merge($items, $batch);
            if (count($batch) < 500) {
                break;
            }
        }
        return $items;
    }

    public static function find($collection, $filter)
    {
        $items = self::list($collection, ['filter' => $filter, 'perPage' => 1]);
        return $items[0] ?? null;
    }

    public static function get($collection, $id)
    {
        try {
            return self::call(fn($c) => $c->get("/api/collections/$collection/records/" . rawurlencode($id)));
        } catch (RuntimeException $e) {
            return null;
        }
    }

    public static function create($collection, array $data)
    {
        return self::call(fn($c) => $c->createRecord($collection, $data));
    }

    public static function update($collection, $id, array $data)
    {
        return self::call(fn($c) => $c->updateRecord($collection, rawurlencode($id), $data));
    }

    public static function delete($collection, $id)
    {
        self::call(fn($c) => $c->deleteRecord($collection, rawurlencode($id)));
    }

    // Quotes a value for use inside a PocketBase filter expression.
    public static function quote($value)
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value) . '"';
    }

    public static function setting($key, $default = null)
    {
        $row = self::find('settings', 'key=' . self::quote($key));
        return $row && array_key_exists('value', $row) && $row['value'] !== null ? $row['value'] : $default;
    }

    public static function setSetting($key, $value)
    {
        $row = self::find('settings', 'key=' . self::quote($key));
        if ($row) {
            self::update('settings', $row['id'], ['value' => $value]);
        } else {
            self::create('settings', ['key' => $key, 'value' => $value]);
        }
    }
}
