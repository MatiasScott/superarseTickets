<?php

return [
	// Configuración de Teléfono y SMS
	'default_provider' => env('SMS_PROVIDER', 'twilio'),

	// Twilio Configuration
	'twilio' => [
		'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
		'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
		'phone_number' => env('TWILIO_PHONE_NUMBER', ''),
		'enabled' => env('TWILIO_ENABLED', false),
	],

	// AWS SNS Configuration
	'sns' => [
		'key' => env('AWS_ACCESS_KEY_ID', ''),
		'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
		'region' => env('AWS_REGION', 'us-east-1'),
		'enabled' => env('SNS_ENABLED', false),
	],

	// Nexmo/Vonage Configuration
	'nexmo' => [
		'api_key' => env('NEXMO_API_KEY', ''),
		'api_secret' => env('NEXMO_API_SECRET', ''),
		'from' => env('NEXMO_FROM_NUMBER', ''),
		'enabled' => env('NEXMO_ENABLED', false),
	],

	// Formats
	'formats' => [
		'default' => 'E.164', // +34600000000
		'display' => 'international', // +34 (600) 000-000
	],

	// Validation
	'validation' => [
		'min_length' => 9,
		'max_length' => 15,
		'allowed_countries' => ['ES', 'US', 'MX', 'AR', 'CO', 'CL', 'PE'],
	],
];
