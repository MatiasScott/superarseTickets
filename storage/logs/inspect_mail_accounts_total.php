<?php

define('APP_PATH', dirname(__DIR__, 2) . '/app');
require APP_PATH . '/core/Helpers.php';

$fileValue = '(not found)';
$lines = file(dirname(__DIR__, 2) . '/.env', FILE_IGNORE_NEW_LINES);
if (is_array($lines)) {
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), 'MAIL_ACCOUNTS_TOTAL=')) {
            $fileValue = trim(substr($line, strlen('MAIL_ACCOUNTS_TOTAL=')));
        }
    }
}

echo 'file=' . $fileValue . PHP_EOL;
echo 'env_func=' . (string) env('MAIL_ACCOUNTS_TOTAL', 'default') . PHP_EOL;
echo 'server=' . (string) ($_SERVER['MAIL_ACCOUNTS_TOTAL'] ?? '(none)') . PHP_EOL;
echo 'env=' . (string) ($_ENV['MAIL_ACCOUNTS_TOTAL'] ?? '(none)') . PHP_EOL;
