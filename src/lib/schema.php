<?php

function createSystemInformationSchema(PocketBaseClient $pocketBaseClient, $email, $password) {
    $pocketBaseClient->authWithPassword($email, $password);

    $collectionData = [
        'name' => 'system_information',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'hostname',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'os',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'php_version',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'server_software',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'cpu_model',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'cpu_cores',
                'type' => 'number',
                'required' => false,
            ],
            [
                'name' => 'cpu_usage',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'memory_total',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'memory_used',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'memory_available',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'memory_usage',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'uptime',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'load_average',
                'type' => 'json',
                'required' => false,
            ],
            [
                'name' => 'network_info',
                'type' => 'json',
                'required' => false,
            ],
            [
                'name' => 'process_list',
                'type' => 'json',
                'required' => false,
            ],
        ],
    ];

    return $pocketBaseClient->createCollection($collectionData);
}
?>
