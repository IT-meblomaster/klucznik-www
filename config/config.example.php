<?php

return [
    'app' => [
        'name' => 'Template',
        'base_url' => '',
    ],

    'debug' => true,
//    'log_errors' => true,
//    'error_log' => __DIR__ . '/../var/logs/php-error.log',

    'db' => [
        'host' => 'MYSQL_DB_ADRESS',
        'port' => MYSQL_DB_PORT,
        'name' => 'MYSQL_DATABASE_NAME',
        'user' => 'MYSQL_DATABASE_USERNAME',
        'pass' => 'MYSQL_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name' => 'template_www_sess',
        'csrf_key' => 'change_this_random_string',
    ],
];
