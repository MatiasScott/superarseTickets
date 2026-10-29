<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este procedimiento solo puede ejecutarse desde CLI.');
}

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

$autoload = ROOT_PATH . '/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

spl_autoload_register(static function (string $class): void {
    foreach (
        [
            APP_PATH . '/core/' . $class . '.php',
            APP_PATH . '/controllers/' . $class . '.php',
            APP_PATH . '/models/' . $class . '.php',
            APP_PATH . '/services/' . $class . '.php',
        ] as $path
    ) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

require_once APP_PATH . '/core/Helpers.php';

$appConfig = require APP_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

$options = getopt('', [
    'since:',
    'until:',
    'account:',
    'max:',
    'execute',
]);

$sinceRaw = (string) ($options['since'] ?? '2026-10-27T05:00:00Z');
$untilRaw = (string) ($options['until'] ?? '2026-10-29T05:00:00Z');
$accountFilter = trim((string) ($options['account'] ?? ''));
$max = max(1, min(10000, (int) ($options['max'] ?? 500)));

$execute = array_key_exists('execute', $options);

function printResult(array $data): void
{
    echo json_encode(
        $data,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
    ), PHP_EOL;
}

try {
    $since = new DateTimeImmutable($sinceRaw);
    $until = new DateTimeImmutable($untilRaw);

    if ($until <= $since) {
        throw new RuntimeException(
            'La fecha final debe ser posterior a la inicial.'
        );
    }

    $mailbox = new MailboxService();
    $accounts = $mailbox->getAvailableAccounts();

    if ($accountFilter !== '') {
        $accounts = array_values(array_filter(
            $accounts,
            static fn(array $account): bool =>
            (string) ($account['alias'] ?? '') === $accountFilter
        ));
    }

    if (empty($accounts)) {
        throw new RuntimeException(
            'No se encontraron cuentas habilitadas para la consulta.'
        );
    }

    $db = null;
    $controller = null;
    $ticketCfg = [];
    $syncMethod = null;

    if ($execute) {
        $db = Database::getInstance()->connection();
        $controller = new CorreoController();

        $reflection = new ReflectionClass($controller);

        foreach (
            [
                'ensureMailSyncTable',
                'ensureTicketMensajesThreadColumns',
                'ensureTicketMensajesMessageCapacity',
                'ensureReplyAttachmentsTable',
                'ensureAttachmentQueueTable',
                'ensureProcessQueueTable',
            ] as $methodName
        ) {
            $method = $reflection->getMethod($methodName);
            $method->invoke($controller, $db);
        }

        $defaultsMethod = $reflection->getMethod(
            'resolveTicketDefaults'
        );

        $ticketCfg = (array) $defaultsMethod->invoke(
            $controller,
            $db
        );

        $syncMethod = $reflection->getMethod(
            'syncEmailsIntoTickets'
        );
    }

    $results = [];
    $totals = [
        'fetched' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    foreach ($accounts as $account) {
        $alias = trim((string) ($account['alias'] ?? ''));

        if ($alias === '') {
            continue;
        }

        $entry = [
            'account' => $alias,
            'complete' => false,
            'folders_scanned' => 0,
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lockAcquired = false;
        $lockName = substr(
            'atlas:mail:' . hash('sha256', $alias),
            0,
            64
        );

        try {
            if ($execute) {
                $stmt = $db->prepare(
                    'SELECT GET_LOCK(:lock_name, 0)'
                );
                $stmt->execute(['lock_name' => $lockName]);

                $lockAcquired = (int) $stmt->fetchColumn() === 1;

                if (!$lockAcquired) {
                    throw new RuntimeException(
                        'Buzon ocupado por otra sincronizacion.'
                    );
                }
            }

            $fetch = $mailbox->fetchSinceForTicketing(
                $alias,
                $since->format('Y-m-d\TH:i:s\Z'),
                $max,
                $until->format('Y-m-d\TH:i:s\Z')
            );

            $entry['complete'] = !empty($fetch['complete']);
            $entry['folders_scanned'] =
                (int) ($fetch['folders_scanned'] ?? 0);

            $emails = is_array($fetch['emails'] ?? null)
                ? $fetch['emails']
                : [];

            $entry['fetched'] = count($emails);
            $totals['fetched'] += count($emails);

            if (empty($fetch['ok']) || !$entry['complete']) {
                throw new RuntimeException(
                    (string) ($fetch['error']
                        ?? 'Consulta de carpetas incompleta.')
                );
            }

            if ($execute && !empty($emails)) {

                $counts = (array) $syncMethod->invoke(
                    $controller,
                    $db,
                    $mailbox,
                    $emails,
                    $ticketCfg,
                    [],
                    [],
                    [],
                    false
                );


                $entry['created'] =
                    (int) ($counts['created'] ?? 0);

                $entry['updated'] =
                    (int) ($counts['updated'] ?? 0);

                $entry['skipped'] =
                    (int) ($counts['skipped'] ?? 0);

                $errorCount =
                    (int) ($counts['error_count'] ?? 0);

                $entry['omitted'] =
                    (array) ($counts['omitted_breakdown'] ?? []);

                $totals['created'] += $entry['created'];
                $totals['updated'] += $entry['updated'];
                $totals['skipped'] += $entry['skipped'];

                if ($errorCount > 0) {
                    $totals['errors'] += $errorCount;
                    $entry['errors'][] =
                        $errorCount . ' mensaje(s) fallaron al procesarse.';
                }
            }
        } catch (Throwable $e) {
            $entry['errors'][] = $e->getMessage();
            $totals['errors']++;
        } finally {
            if ($lockAcquired) {
                try {
                    $release = $db->prepare(
                        'SELECT RELEASE_LOCK(:lock_name)'
                    );

                    $release->execute([
                        'lock_name' => $lockName,
                    ]);
                } catch (Throwable $e) {
                    $entry['errors'][] =
                        'Error liberando bloqueo: ' . $e->getMessage();
                    $totals['errors']++;
                }
            }
        }

        $results[] = $entry;
    }

    printResult([
        'ok' => $totals['errors'] === 0,
        'mode' => $execute ? 'EXECUTE' : 'DIAGNOSTIC',
        'since' => $sinceRaw,
        'until' => $untilRaw,
        'accounts' => count($results),
        'totals' => $totals,
        'results' => $results,
    ]);

    if ($totals['errors'] > 0) {
        exit(1);
    }
} catch (Throwable $e) {
    printResult([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);

    exit(1);
}
