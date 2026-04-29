<?php

return [
	// Configuración de Correo SMTP
	'driver' => 'smtp', // smtp, sendmail, mailgun, ses
	'from' => [
		'name' => 'ISTS Ticket System',
		'email' => env('MAIL_FROM_EMAIL', 'noreply@ists.local'),
	],

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
