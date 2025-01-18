<?php

function createSystemInformationSchema(PocketBaseClient $pocketBaseClient, $email, $password) {
    $pocketBaseClient->authWithPassword($email, $password);

    $collections = [
        'system_info' => [
            'name' => 'system_info',
            'type' => 'base',
            'fields' => array_merge(
                getHostnameField(),
                getOsField(),
                getPhpVersionField(),
                getServerSoftwareField(),
                getTimestampFields()
            )
        ],
        'cpu_info' => [
            'name' => 'cpu_info',
            'type' => 'base',
            'fields' => array_merge(
                getCpuModelField(),
                getCpuCoresField(),
                getCpuUsageField(),
                getTimestampFields()
            )
        ],
        'memory_info' => [
            'name' => 'memory_info',
            'type' => 'base',
            'fields' => array_merge(
                getMemoryFields(),
                getTimestampFields()
            )
        ],
        'disk_info' => [
            'name' => 'disk_info',
            'type' => 'base',
            'fields' => array_merge(
                getDiskFields(),
                getTimestampFields()
            )
        ],
        'swap_info' => [
            'name' => 'swap_info',
            'type' => 'base',
            'fields' => array_merge(
                getSwapFields(),
                getTimestampFields()
            )
        ],
        'network_info' => [
            'name' => 'network_info',
            'type' => 'base',
            'fields' => array_merge(
                getNetworkInfoField(),
                getNetworkSpeedField(),
                getTimestampFields()
            )
        ],
        'process_info' => [
            'name' => 'process_info',
            'type' => 'base',
            'fields' => array_merge(
                getProcessListField(),
                getTimestampFields()
            )
        ],
        'uptime_info' => [
            'name' => 'uptime_info',
            'type' => 'base',
            'fields' => array_merge(
                getUptimeField(),
                getTimestampFields()
            )
        ],
        'load_average_info' => [
            'name' => 'load_average_info',
            'type' => 'base',
            'fields' => array_merge(
                getLoadAverageField(),
                getTimestampFields()
            )
        ],
        'user_info' => [
            'name' => 'user_info',
            'type' => 'base',
            'fields' => array_merge(
                getConnectedUsersField(),
                getTimestampFields()
            )
        ],
        'security_info' => [
            'name' => 'security_info',
            'type' => 'base',
            'fields' => array_merge(
                getFirewallStatusField(),
                getSecurityUpdatesField(),
                getTimestampFields()
            )
        ],
        'log_info' => [
            'name' => 'log_info',
            'type' => 'base',
            'fields' => array_merge(
                getRecentLogEntriesField(),
                getTimestampFields()
            )
        ],
        'temperature_info' => [
            'name' => 'temperature_info',
            'type' => 'base',
            'fields' => array_merge(
                getTemperatureField(),
                getTimestampFields()
            )
        ]
    ];
    

    foreach ($collections as $collectionData) {
        $pocketBaseClient->createCollection($collectionData);
    }
}

function getHostnameField() {
    return [
        [
            'name' => 'hostname',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getOsField() {
    return [
        [
            'name' => 'os',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getPhpVersionField() {
    return [
        [
            'name' => 'php_version',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getServerSoftwareField() {
    return [
        [
            'name' => 'server_software',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getCpuModelField() {
    return [
        [
            'name' => 'cpu_model',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getCpuCoresField() {
    return [
        [
            'name' => 'cpu_cores',
            'type' => 'number',
            'required' => false,
        ]
    ];
}

function getCpuUsageField() {
    return [
        [
            'name' => 'cpu_usage',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getMemoryFields() {
    return [
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
        ]
    ];
}

function getDiskFields() {
    return [
        [
            'name' => 'disk_total',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'disk_used',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'disk_available',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'disk_usage',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getSwapFields() {
    return [
        [
            'name' => 'swap_total',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'swap_used',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'swap_free',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'swap_usage',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getNetworkInfoField() {
    return [
        [
            'name' => 'network_info',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getProcessListField() {
    return [
        [
            'name' => 'process_list',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getUptimeField() {
    return [
        [
            'name' => 'uptime',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getLoadAverageField() {
    return [
        [
            'name' => 'load_average',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getNetworkSpeedField() {
    return [
        [
            'name' => 'network_speed',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getConnectedUsersField() {
    return [
        [
            'name' => 'connected_users',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getFirewallStatusField() {
    return [
        [
            'name' => 'firewall_status',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getRecentLogEntriesField() {
    return [
        [
            'name' => 'recent_log_entries',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getSecurityUpdatesField() {
    return [
        [
            'name' => 'security_updates',
            'type' => 'json',
            'required' => false,
        ]
    ];
}

function getTemperatureField() {
    return [
        [
            'name' => 'temperature',
            'type' => 'text',
            'required' => false,
        ]
    ];
}

function getTimestampFields() {
    return [
        [
            'name' => 'created',
            'type' => 'autodate',
            'required' => true,
            'onCreate' => true,
            'onUpdate' => false,
        ],
        [
            'name' => 'updated',
            'type' => 'autodate',
            'required' => true,
            'onCreate' => true,
            'onUpdate' => true,
        ]
    ];
}
?>
