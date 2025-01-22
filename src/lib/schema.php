<?php

function createSystemInformationSchema(PocketBaseClient $pocketBaseClient, $email, $password)
{
    $pocketBaseClient->authWithPassword($email, $password);

    $collections = [
        'global_info' => [
            'id' => 'pbc_global_info',
            'name' => 'global_info',
            'type' => 'base',
        ],
        'server' => [
            'id' => 'pbc_server',
            'name' => 'server',
            'type' => 'base',
        ],
        'host' => [
            'id' => 'pbc_host',
            'name' => 'host',
            'type' => 'base',
        ],
        'cpu' => [
            'id' => 'pbc_cpu',
            'name' => 'cpu',
            'type' => 'base',
        ],
        'state' => [
            'id' => 'pbc_state',
            'name' => 'state',
            'type' => 'base',
        ]
    ];

   
    foreach ($collections as $collectionData) {
        $pocketBaseClient->createCollection($collectionData);
    }

   
    $pocketBaseClient->updateCollection('pbc_global_info', [
        'fields' => array_merge(
            getNowField(),
            getOnlineField(),
            getTimestampFields()
        )
    ]);

    $pocketBaseClient->updateCollection('pbc_server', [
        'fields' => array_merge(
            getNameField(),
            getCountryCodeField(),
            getLastActiveField(),
            getGlobalInfoIdField(),
            getTimestampFields()
        )
    ]);

    $pocketBaseClient->updateCollection('pbc_host', [
        'fields' => array_merge(
            getPlatformField(),
            getMemTotalField(),
            getDiskTotalField(),
            getSwapTotalField(),
            getArchField(),
            getBootTimeField(),
            getServerIdField(),
            getTimestampFields()
        )
    ]);

    $pocketBaseClient->updateCollection('pbc_cpu', [
        'fields' => array_merge(
            getDescriptionField(),
            getHostIdField(),
            getTimestampFields()
        )
    ]);

    $pocketBaseClient->updateCollection('pbc_state', [
        'fields' => array_merge(
            getCpuUsageField(),
            getMemUsedField(),
            getDiskUsedField(),
            getNetInTransferField(),
            getNetOutTransferField(),
            getNetInSpeedField(),
            getNetOutSpeedField(),
            getUptimeField(),
            getServerIdField(),
            getTimestampFields()
        )
    ]);
}

function getNowField()
{
    return [
        [
            'name' => 'now',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getOnlineField()
{
    return [
        [
            'name' => 'online',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getServerIdField()
{
    return [
        [
            'name' => 'server_id',
            'type' => 'relation',
            'maxSelect' => 1,
            'collectionId' => 'pbc_server',
            'cascadeDelete' => true,
            'required' => true,
        ]
    ];
}

function getNameField()
{
    return [
        [
            'name' => 'name',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getCountryCodeField()
{
    return [
        [
            'name' => 'country_code',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getLastActiveField()
{
    return [
        [
            'name' => 'last_active',
            'type' => 'autodate',
            'required' => true,
            'onCreate' => true,
            'onUpdate' => true,
        ]
    ];
}

function getGlobalInfoIdField()
{
    return [
        [
            'name' => 'global_info_id',
            'type' => 'relation',
            'maxSelect' => 1,
            'collectionId' => 'pbc_global_info',
            'cascadeDelete' => true,
            'required' => true,
        ]
    ];
}

function getPlatformField()
{
    return [
        [
            'name' => 'platform',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getMemTotalField()
{
    return [
        [
            'name' => 'mem_total',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getDiskTotalField()
{
    return [
        [
            'name' => 'disk_total',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getSwapTotalField()
{
    return [
        [
            'name' => 'swap_total',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getArchField()
{
    return [
        [
            'name' => 'arch',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getBootTimeField()
{
    return [
        [
            'name' => 'boot_time',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getDescriptionField()
{
    return [
        [
            'name' => 'description',
            'type' => 'text',
            'required' => true,
        ]
    ];
}

function getHostIdField()
{
    return [
        [
            'name' => 'host_id',
            'type' => 'relation',
            'maxSelect' => 1,
            'collectionId' => 'pbc_host',
            'cascadeDelete' => true,
            'required' => true,
        ]
    ];
}

function getCpuUsageField()
{
    return [
        [
            'name' => 'cpu_usage',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getMemUsedField()
{
    return [
        [
            'name' => 'mem_used',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getDiskUsedField()
{
    return [
        [
            'name' => 'disk_used',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getNetInTransferField()
{
    return [
        [
            'name' => 'net_in_transfer',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getNetOutTransferField()
{
    return [
        [
            'name' => 'net_out_transfer',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getNetInSpeedField()
{
    return [
        [
            'name' => 'net_in_speed',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getNetOutSpeedField()
{
    return [
        [
            'name' => 'net_out_speed',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getUptimeField()
{
    return [
        [
            'name' => 'uptime',
            'type' => 'number',
            'required' => true,
        ]
    ];
}

function getTimestampFields()
{
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
