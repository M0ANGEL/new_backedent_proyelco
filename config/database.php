<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    // 'connections' => [

    //     'sqlite' => [
    //         'driver' => 'sqlite',
    //         'url' => env('DATABASE_URL'),
    //         'database' => env('DB_DATABASE', database_path('database.sqlite')),
    //         'prefix' => '',
    //         'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    //     ],

    //     'mysql' => [
    //         'driver' => 'mysql',
    //         'url' => env('DATABASE_URL'),
    //         'host' => env('DB_HOST', '127.0.0.1'),
    //         'port' => env('DB_PORT', '3306'),
    //         'database' => env('DB_DATABASE', 'forge'),
    //         'username' => env('DB_USERNAME', 'forge'),
    //         'password' => env('DB_PASSWORD', ''),
    //         'unix_socket' => env('DB_SOCKET', ''),
    //         'charset' => 'utf8mb4',
    //         'collation' => 'utf8mb4_unicode_ci',
    //         'prefix' => '',
    //         'prefix_indexes' => true,
    //         'strict' => true,
    //         'engine' => null,
    //         'options' => extension_loaded('pdo_mysql') ? array_filter([
    //             PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    //         ]) : [],
    //     ],

    //     'pgsql' => [
    //         'driver' => 'pgsql',
    //         'url' => env('DATABASE_URL'),
    //         'host' => env('DB_HOST', '127.0.0.1'),
    //         'port' => env('DB_PORT', '5432'),
    //         'database' => env('DB_DATABASE', 'forge'),
    //         'username' => env('DB_USERNAME', 'forge'),
    //         'password' => env('DB_PASSWORD', ''),
    //         'charset' => 'utf8',
    //         'prefix' => '',
    //         'prefix_indexes' => true,
    //         'search_path' => 'public',
    //         'sslmode' => 'prefer',
    //     ],

    //      'sqlsrv_sinco' => [
    //         'driver' => 'sqlsrv',
    //         'host' => env('DB_HOST', 'datamart.sincoerp.com'),
    //         'port' => env('DB_PORT', '4263'),
    //         'database' => env('DB_DATABASE', 'SincoProyelcoDW'),
    //         'username' => env('DB_USERNAME', 'SincoProyelcoDW'),
    //         'password' => env('DB_PASSWORD', 'SincoProyelcoDW.544.4011'),
    //         'charset' => 'utf8',
    //         'prefix' => '',
    //         'prefix_indexes' => true,
    //         'encrypt' => env('DB_ENCRYPT', 'yes'), // Laravel >=9
    //         'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true), // boolean
    //         'options' => [
    //             // vacío o agrega solo lo que soporta sqlsrv
    //         ],
    //     ],

    // ],

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'sqlsrv_sinco' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_SINCO_HOST', 'datamart.sincoerp.com'),  // ← Cambiado
            'port' => env('DB_SINCO_PORT', '4263'),                  // ← Cambiado
            'database' => env('DB_SINCO_DATABASE', 'SincoProyelcoDW'), // ← Cambiado
            'username' => env('DB_SINCO_USERNAME', 'SincoProyelcoDW'), // ← Cambiado
            'password' => env('DB_SINCO_PASSWORD', 'SincoProyelcoDW.544.4011'), // ← Cambiado
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_SINCO_ENCRYPT', 'yes'),             // ← Cambiado
            'trust_server_certificate' => env('DB_SINCO_TRUST_SERVER_CERTIFICATE', true),
            'options' => [],
        ],
    ],


    'migrations' => 'migrations',


    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
