<?php

$mailAccounts = [];
$accountsTotal = (int) env('MAIL_ACCOUNTS_TOTAL', 10);
if ($accountsTotal < 1) {
	$accountsTotal = 1;
}

for ($i = 1; $i <= $accountsTotal; $i++) {
	$prefix = 'MAIL_ACCOUNT_' . $i . '_';
	$email = trim((string) env($prefix . 'EMAIL', ''));
	if ($email === '') {
		continue;
	}

	$mailAccounts[] = [
		'alias' => trim((string) env($prefix . 'ALIAS', 'acc' . $i)),
		'name' => trim((string) env($prefix . 'NAME', 'Cuenta ' . $i)),
		'email' => $email,
		'username' => trim((string) env($prefix . 'USERNAME', env('MAIL_USERNAME', ''))),
		'password' => trim((string) env($prefix . 'PASSWORD', env('MAIL_PASSWORD', ''))),
		'host' => trim((string) env($prefix . 'HOST', env('MAIL_HOST', 'smtp.mailtrap.io'))),
		'port' => (int) env($prefix . 'PORT', env('MAIL_PORT', 465)),
		'encryption' => trim((string) env($prefix . 'ENCRYPTION', env('MAIL_ENCRYPTION', 'tls'))),
		'enabled' => env($prefix . 'ENABLED', true),
	];
}

if (empty($mailAccounts)) {
	$mailAccounts[] = [
		'alias' => 'default',
		'name' => trim((string) env('MAIL_FROM_NAME', 'ISTS Ticket System')),
		'email' => trim((string) env('MAIL_FROM_EMAIL', 'noreply@ists.local')),
		'username' => trim((string) env('MAIL_USERNAME', '')),
		'password' => trim((string) env('MAIL_PASSWORD', '')),
		'host' => trim((string) env('MAIL_HOST', 'smtp.mailtrap.io')),
		'port' => (int) env('MAIL_PORT', 465),
		'encryption' => trim((string) env('MAIL_ENCRYPTION', 'tls')),
		'enabled' => true,
	];
}

return [
	// Configuración de Correo SMTP
	'driver' => env('MAIL_DRIVER', 'smtp'), // smtp, sendmail, mailgun, ses
	'from' => [
		'name' => env('MAIL_FROM_NAME', 'ISTS Ticket System'),
		'email' => env('MAIL_FROM_EMAIL', 'noreply@ists.local'),
	],

	// Multi-cuenta de correo
	'accounts' => $mailAccounts,
	'account_strategy' => env('MAIL_ACCOUNT_STRATEGY', 'round_robin'), // round_robin, first
	'default_account_alias' => env('MAIL_DEFAULT_ACCOUNT_ALIAS', 'default'),

	// SMTP Configuration
	'smtp' => [
		'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
		'port' => env('MAIL_PORT', 465),
		'username' => env('MAIL_USERNAME', ''),
		'password' => env('MAIL_PASSWORD', ''),
		'encryption' => env('MAIL_ENCRYPTION', 'tls'), // tls, ssl
	],

	// Sendmail Configuration
	'sendmail' => [
		'path' => env('SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
	],

	// Retry Configuration
	'retry' => [
		'max_attempts' => 3,
		'retry_delay' => 60, // segundos
	],

	// Queue Configuration (para envíos en segundo plano)
	'queue' => [
		'enabled' => env('MAIL_QUEUE_ENABLED', false),
		'driver' => 'database', // database, redis, file
	],

	// Email Templates Paths
	'templates' => [
		'path' => APP_PATH . '/views/emails',
		'default_layout' => 'layout',
	],
];
