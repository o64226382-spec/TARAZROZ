<?php
/**
 * تنظیمات پایگاه داده
 */

return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysqli',
            'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
            'database' => defined('DB_NAME') ? DB_NAME : 'tarazroz_tarazdb',
            'username' => defined('DB_USER') ? DB_USER : 'tarazroz_tarazuser',
            'password' => defined('DB_PASS') ? DB_PASS : 'NyLue-hRh2OP9c;8',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ]
    ]
];
