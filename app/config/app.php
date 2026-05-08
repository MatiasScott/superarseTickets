<?php

return [
	'name' => 'ISTS Ticket',
	'url' => (string) env('APP_URL', '/istsTicket/public'),
	'timezone' => 'America/Guayaquil',
	'debug' => strtolower((string) env('APP_DEBUG', 'true')) === 'true',
];
