<?php

return [
    'host' => (string) env('DB_HOST', 'localhost'),
    'port' => (string) env('DB_PORT', '3306'),
    'database' => (string) env('DB_DATABASE', (string) env('DB_NAME', 'istsTicket')),
    'username' => (string) env('DB_USERNAME', (string) env('DB_USER', 'root')),
    'password' => (string) env('DB_PASSWORD', 'Superarse.2025'),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    'collation' => 'utf8mb4_unicode_ci',
];
