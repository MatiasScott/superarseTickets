<?php

define('APP_PATH', dirname(__DIR__, 2) . '/app');
require APP_PATH . '/core/Helpers.php';

$cfg = require APP_PATH . '/config/mail.php';
$accounts = is_array($cfg['accounts'] ?? null) ? $cfg['accounts'] : [];

echo 'count=' . count($accounts) . PHP_EOL;
foreach ($accounts as $a) {
    echo (string) ($a['alias'] ?? '')
        . '|'
        . (string) ($a['email'] ?? '')
        . '|'
        . ((isset($a['enabled']) && $a['enabled']) ? 'true' : 'false')
        . PHP_EOL;
}
