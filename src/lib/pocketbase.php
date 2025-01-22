<?php

class PocketBaseClient
{
    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    public function createCollection($data)
    {
        return $this->post('/api/collections', $data);
    }

    public function updateCollection($collectionIdOrName, $data)
    {
        return $this->patch("/api/collections/$collectionIdOrName", $data);
    }

    private function request($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $headers = ['Content-Type: application/json'];
        if ($data) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Length: ' . strlen($jsonData);
        }

        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Request Error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    public function get($endpoint)
    {
        return $this->request('GET', $endpoint);
    }

    public function post($endpoint, $data)
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function put($endpoint, $data)
    {
        return $this->request('PUT', $endpoint, $data);
    }

    public function patch($endpoint, $data)
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    public function delete($endpoint)
    {
        return $this->request('DELETE', $endpoint);
    }

    public function getRecords($collection)
    {
        return $this->get("/api/collections/$collection/records");
    }

    public function createRecord($collection, $data)
    {
        return $this->post("/api/collections/$collection/records", $data);
    }

    public function updateRecord($collection, $id, $data)
    {
        return $this->put("/api/collections/$collection/records/$id", $data);
    }

    public function deleteRecord($collection, $id)
    {
        return $this->delete("/api/collections/$collection/records/$id");
    }

    public function subscribeToRealtime($collection, $recordId = null, $callback)
    {
        $url = $this->baseUrl . '/api/realtime';
        if ($recordId) {
            $url .= "/collections/$collection/records/$recordId";
        } else {
            $url .= "/collections/$collection";
        }

        $headers = [];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($callback) {
            $lines = explode("\n", trim($data));
            foreach ($lines as $line) {
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    $event = json_decode($json, true);
                    $callback($event);
                }
            }
            return strlen($data);
        });

        curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('SSE Request Error: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    public function authWithPassword($email, $password)
    {
        $response = $this->post('/api/collections/_superusers/auth-with-password', [
            'identity' => $email,
            'password' => $password,
        ]);

        if (isset($response['token'])) {
            $this->token = $response['token'];
        } else {
            throw new Exception('Authentication failed');
        }
    }
}
?>