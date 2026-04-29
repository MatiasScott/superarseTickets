<?php

return [
	// Bots y Automatización disponibles
	'bots' => [
		'whatsapp' => [
			'enabled' => env('BOT_WHATSAPP_ENABLED', false),
			'provider' => 'twilio', // twilio, waba, baileys
			'phone_number' => env('BOT_WHATSAPP_PHONE', ''),
			'api_key' => env('BOT_WHATSAPP_API_KEY', ''),
			'webhook_url' => env('BOT_WHATSAPP_WEBHOOK', ''),
			'greeting_message' => 'Hola, ¿en qué podemos ayudarte?',
		],

		'telegram' => [
			'enabled' => env('BOT_TELEGRAM_ENABLED', false),
			'token' => env('BOT_TELEGRAM_TOKEN', ''),
			'webhook_url' => env('BOT_TELEGRAM_WEBHOOK', ''),
			'greeting_message' => 'Bienvenido al bot de ISTS',
		],

		'messenger' => [
			'enabled' => env('BOT_MESSENGER_ENABLED', false),
			'page_access_token' => env('BOT_MESSENGER_TOKEN', ''),
			'verify_token' => env('BOT_MESSENGER_VERIFY_TOKEN', ''),
			'webhook_url' => env('BOT_MESSENGER_WEBHOOK', ''),
		],

		'email' => [
			'enabled' => env('BOT_EMAIL_ENABLED', true),
			'auto_reply_enabled' => true,
			'auto_reply_template' => 'Gracias por tu correo. Responderemos pronto.',
			'forwarding_enabled' => true,
		],

		'sms' => [
			'enabled' => env('BOT_SMS_ENABLED', false),
			'auto_reply' => true,
			'two_way_messaging' => true,
		],
	],

	// Automatizaciones
	'automations' => [
		'welcome_email' => [
			'enabled' => true,
			'trigger' => 'on_user_created',
			'template' => 'welcome',
			'delay_seconds' => 0,
		],

		'password_reset' => [
			'enabled' => true,
			'trigger' => 'on_password_reset_requested',
			'template' => 'password_reset',
			'expire_hours' => 24,
		],

		'ticket_created' => [
			'enabled' => true,
			'trigger' => 'on_ticket_created',
			'notify_assignee' => true,
			'notify_customer' => true,
			'template' => 'ticket_created',
		],

		'ticket_updated' => [
			'enabled' => true,
			'trigger' => 'on_ticket_updated',
			'notify_watchers' => true,
			'template' => 'ticket_updated',
		],

		'campaign_scheduled' => [
			'enabled' => true,
			'trigger' => 'on_campaign_created',
			'allow_scheduling' => true,
			'max_recipients_per_batch' => 100,
		],
	],

	// Plantillas de respuesta automática
	'templates' => [
		'greeting' => 'Hola {nombre}, ¿en qué podemos ayudarte?',
		'help' => 'Puedo ayudarte con: 1) Crear un ticket, 2) Ver mis tickets, 3) Hablar con un agente',
		'error' => 'Disculpa, no entendí tu mensaje. Intenta de nuevo o escribe "ayuda"',
		'closing' => 'Gracias por usar nuestro servicio. ¡Que tengas un excelente día!',
	],

	// Horarios de disponibilidad
	'availability' => [
		'enabled' => true,
		'timezone' => 'Europe/Madrid',
		'business_hours' => [
			'monday' => ['start' => '09:00', 'end' => '18:00'],
			'tuesday' => ['start' => '09:00', 'end' => '18:00'],
			'wednesday' => ['start' => '09:00', 'end' => '18:00'],
			'thursday' => ['start' => '09:00', 'end' => '18:00'],
			'friday' => ['start' => '09:00', 'end' => '17:00'],
			'saturday' => ['start' => '', 'end' => ''],
			'sunday' => ['start' => '', 'end' => ''],
		],
		'out_of_hours_message' => 'Estamos fuera de horario. Responderemos cuando volvamos.',
	],

	// Límites y Throttling
	'rate_limiting' => [
		'enabled' => true,
		'max_messages_per_minute' => 60,
		'max_messages_per_hour' => 1000,
		'block_duration_minutes' => 15,
	],

	// Logging y Monitoreo
	'logging' => [
		'enabled' => true,
		'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
		'store_conversations' => true,
		'retention_days' => 90,
	],
];
