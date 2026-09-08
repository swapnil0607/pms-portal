<?php

// Check if running on localhost / CLI
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || (php_sapi_name() === 'cli') || (($_SERVER['HTTP_HOST'] ?? '') === 'localhost');

return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => $isLocal ? 'demo_pms' : 'eduriser_pms',
    'username' => $isLocal ? 'root' : (getenv('DB_USER') ?: 'root'),
    'password' => $isLocal ? '' : (getenv('DB_PASS') ?: ''),
    'charset' => 'utf8mb4',
];

