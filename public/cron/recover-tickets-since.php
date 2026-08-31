<?php

declare(strict_types=1);

session_start();

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    foreach ([
        APP_PATH . '/core/' . $class . '.php',
        APP_PATH . '/controllers/' . $class . '.php',
        APP_PATH . '/models/' . $class . '.php',
        APP_PATH . '/services/' . $class . '.php',
    ] as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

require_once APP_PATH . '/core/Helpers.php';
$appConfig = require APP_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

header('Content-Type: application/json; charset=UTF-8');

$providedToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$expectedToken = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
    exit;
}

// Fecha desde la cual recuperar tickets. Por defecto: martes 2026-08-25.
$sinceRaw = trim((string) ($_GET['since'] ?? $_POST['since'] ?? ''));
if ($sinceRaw === '') {
    $sinceRaw = '2026-08-25T00:00:00Z';
}
$sinceTs = strtotime($sinceRaw);
if ($sinceTs === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Fecha `since` invalida. Usa formato ISO UTC, p.ej. 2026-08-25T00:00:00Z.']);
    exit;
}
$sinceIso = gmdate('Y-m-d\TH:i:s\Z', $sinceTs);

// Limite de correos por cuenta en una ejecucion.
$maxPerAccount = max(1, min(500, (int) ($_GET['max'] ?? $_POST['max'] ?? 200)));

// Cuentas opcionales a incluir (por defecto todas habilitadas).
$accountFilter = trim((string) ($_GET['account_alias'] ?? $_POST['account_alias'] ?? ''));

try {
    $db = Database::getInstance()->connection();
    $controller = new CorreoController();
    $reflection = new ReflectionClass('CorreoController');

    $resolveDefaults = $reflection->getMethod('resolveTicketDefaults');
    $resolveDefaults->setAccessible(true);
    $ticketCfg = (array) $resolveDefaults->invoke($controller, $db);

    $syncMethod = $reflection->getMethod('syncEmailsIntoTickets');
    $syncMethod->setAccessible(true);

    $mailbox = new MailboxService();
    $accounts = $mailbox->getAvailableAccounts();

    $perAccount = [];
    $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'fetched' => 0];

    foreach ($accounts as $account) {
        $alias = trim((string) ($account['alias'] ?? ''));
        if ($alias === '') {
            continue;
        }
        if ($accountFilter !== '' && $alias !== $accountFilter) {
            continue;
        }

        $entry = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => null];

        $fetch = $mailbox->fetchSinceForTicketing($alias, $sinceIso, $maxPerAccount);
        if (!$fetch['ok']) {
            $entry['error'] = (string) ($fetch['error'] ?? 'No se pudo consultar la bandeja.');
            $perAccount[$alias] = $entry;
            continue;
        }

        $emails = is_array($fetch['emails'] ?? null) ? $fetch['emails'] : [];
        $entry['fetched'] = count($emails);
        $totals['fetched'] += count($emails);

        if (!empty($emails)) {
            $counts = (array) $syncMethod->invoke($controller, $db, $mailbox, $emails, $ticketCfg);

            $entry['created'] = (int) ($counts['created'] ?? 0);
            $entry['updated'] = (int) ($counts['updated'] ?? 0);
            $entry['skipped'] = (int) ($counts['skipped'] ?? 0);
            $entry['omitted'] = (array) ($counts['omitted_breakdown'] ?? [
                'ya_procesado' => 0,
                'grupo_actualizado' => 0,
                'contacto_invalido' => 0,
                'error' => 0,
            ]);

            $totals['created'] += $entry['created'];
            $totals['updated'] += $entry['updated'];
            $totals['skipped'] += $entry['skipped'];
        }

        $perAccount[$alias] = $entry;
    }

    echo json_encode([
        'ok' => true,
        'service' => 'recover-tickets-since',
        'since' => $sinceIso,
        'max_per_account' => $maxPerAccount,
        'totals' => $totals,
        'per_account' => $perAccount,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
