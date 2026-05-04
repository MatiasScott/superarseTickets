<?php

define('APP_PATH', dirname(__DIR__, 2) . '/app');
require APP_PATH . '/core/Helpers.php';
require APP_PATH . '/services/GraphMailService.php';
require APP_PATH . '/services/MailService.php';
require APP_PATH . '/services/MailboxService.php';

$_SESSION = [];

$mailbox = new MailboxService();
$accounts = $mailbox->getAvailableAccounts();

if (empty($accounts)) {
    echo "NO_ACCOUNTS\n";
    exit(0);
}

foreach ($accounts as $acc) {
    $alias = (string) ($acc['alias'] ?? '');
    $email = (string) ($acc['email'] ?? '');

    $result = $mailbox->verifyConnection($alias, true);
    $ok = !empty($result['ok']) ? 'OK' : 'FAIL';
    $error = (string) ($result['error'] ?? '');
    $error = str_replace(["\r", "\n", "|"], [' ', ' ', '/'], $error);

    echo $alias . '|' . $email . '|' . $ok . '|' . $error . "\n";
}
