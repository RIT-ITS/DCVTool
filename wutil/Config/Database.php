<?php
// config/database.php
return [
    'connections' => [
        'default' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST'),
            'port' => 5432,
            'database' => getenv('DB_NAME'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASS')
        ],
        'webctrl' => [
            'driver' => 'pgsql',
            'host' => getenv('WCT_DB_HOST'),
            'port' => 5432,
            'database' => getenv('WCT_DB_NAME'),
            'username' => getenv('WCT_DB_USER'),
            'password' => getenv('WCT_DB_PASS')
        ],
        'webctrl_main' => [
            'driver' => 'pgsql',
            'host' => getenv('WCT_DB_HOST'),
            'port' => 5432,
            'database' => getenv('WCT_DB_ALT_NAME'),
            'username' => getenv('WCT_DB_WUSERNAME'),
            'password' => getenv('WCT_DB_WPASSWORD')
        ]
    ]
];
