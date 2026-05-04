<?php

$envFile = dirname(__DIR__, 2) . '/.env';
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$found = [];
$keys = [];
if (is_array($lines)) {
    foreach ($lines as $lineNo => $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2) + [null, ''];
        $k = trim((string) $name);
        $v = trim((string) $value);
        if ($k !== '') {
            $keys[] = $k;
            if (str_contains($k, 'MAIL_ACCOUNTS_TOTAL')) {
                $found[] = [
                    'line' => $lineNo + 1,
                    'raw' => $line,
                    'key' => $k,
                    'key_hex' => implode(' ', str_split(bin2hex($k), 2)),
                    'value' => $v,
                ];
            }
        }
    }
}

echo 'lines=' . (is_array($lines) ? count($lines) : 0) . PHP_EOL;
echo 'keys=' . count($keys) . PHP_EOL;
echo 'has_mail_accounts_total=' . (in_array('MAIL_ACCOUNTS_TOTAL', $keys, true) ? 'yes' : 'no') . PHP_EOL;
if (empty($found)) {
    echo "found_entries=0\n";
} else {
    foreach ($found as $entry) {
        echo 'line=' . $entry['line'] . PHP_EOL;
        echo 'raw=' . $entry['raw'] . PHP_EOL;
        echo 'key=' . $entry['key'] . PHP_EOL;
        echo 'key_hex=' . $entry['key_hex'] . PHP_EOL;
        echo 'value=' . $entry['value'] . PHP_EOL;
    }
}
