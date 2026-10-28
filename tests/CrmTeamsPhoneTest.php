<?php

// Run with: php tests\CrmTeamsPhoneTest.php
define('APP_PATH', dirname(__DIR__) . '\app');
require APP_PATH . '\services\CrmAdvisorTeamService.php';
require APP_PATH . '\services\PhoneNumberPolicy.php';

function expect(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

// --- Equipos: reglas históricas preservadas ---
$legacy = [
	'Lizbeth Ochoa' => 'EQUIPO MAÑANA',
	'Jennifer Betancourt' => 'EQUIPO MAÑANA',
	'Melany Artieda' => 'EQUIPO MAÑANA',
	'Melany Vásquez' => 'EQUIPO NOCHE',
	'Luis Granja' => 'Luis Granja',
	'Mayra Segarra' => 'Mayra Segarra',
	'Noemi Toro' => 'Noemi Toro',
	'Matias Valdivieso' => '',
	'' => '',
];
foreach ($legacy as $asesor => $expected) {
	expect(CrmAdvisorTeamService::legacyCreatorFor($asesor) === $expected, "Regla histórica rota para '{$asesor}'");
}

// --- Equipos: asignación dinámica ---
expect(CrmAdvisorTeamService::creatorForTeam('noche', 'Nueva Asesora') === 'EQUIPO NOCHE', 'Nuevo asesor en equipo noche');
expect(CrmAdvisorTeamService::creatorForTeam('manana', 'Nueva Asesora') === 'EQUIPO MAÑANA', 'Nuevo asesor en equipo mañana');
expect(CrmAdvisorTeamService::creatorForTeam('propio', 'Nueva Asesora') === 'Nueva Asesora', 'Asesor individual');
expect(CrmAdvisorTeamService::creatorForTeam(null, 'Nueva Asesora') === '', 'Sin asignación');
expect(CrmAdvisorTeamService::normalizeTeam('hack') === null, 'Equipo inválido');

// --- Celulares ---
$phoneCases = [
	// Formatos históricos de Ecuador (siguen funcionando)
	'+593987654321' => '+593987654321',
	'0987654321' => '+593987654321',
	'987654321' => '+593987654321',
	'593987654321' => '+593987654321',
	'+593 98 765 4321' => '+593987654321',
	'+5930987654321' => '+593987654321',
	// Internacionales
	'+573001234567' => '+573001234567',
	'+51 987 654 321' => '+51987654321',
	'+1 (305) 555-1234' => '+13055551234',
	'+34612345678' => '+34612345678',
	'+584121234567' => '+584121234567',
	'+5804121234567' => '+584121234567',
	'0057 300 123 4567' => '+573001234567',
	'+393331234567' => '+393331234567',
	// Inválidos
	'3001234567' => '',
	'+593123' => '',
	'+593812345678' => '',
	'+57123' => '',
	'+999123456789' => '',
	'abc' => '',
	'' => '',
];
foreach ($phoneCases as $input => $expected) {
	$actual = PhoneNumberPolicy::normalize((string) $input);
	expect($actual === $expected, "normalize('{$input}') = '{$actual}', esperado '{$expected}'");
}

$split = PhoneNumberPolicy::split('+18095551234');
expect($split !== null && $split['iso'] === 'DO', 'Detecta República Dominicana por código de área');
$split = PhoneNumberPolicy::split('+13055551234');
expect($split !== null && $split['iso'] === 'US', 'Código +1 por defecto Estados Unidos');
$split = PhoneNumberPolicy::split('+593987654321');
expect($split !== null && $split['iso'] === 'EC' && $split['national'] === '987654321', 'Divide Ecuador');
expect(count(PhoneNumberPolicy::countries()) > 200, 'Lista completa de países');

// --- Paridad PHP/JS (misma regla en el navegador) ---
$node = trim((string) shell_exec('where node 2>NUL'));
if ($node !== '') {
	$script = 'global.window = { ATLAS_PHONE_COUNTRIES: ' . json_encode(PhoneNumberPolicy::countries(), JSON_UNESCAPED_UNICODE) . ' };'
		. 'require(' . json_encode(dirname(__DIR__) . '\public\js\phone-country.js') . ');'
		. 'const cases = ' . json_encode(array_map('strval', array_keys($phoneCases))) . ';'
		. 'console.log(JSON.stringify(cases.map((c) => window.AtlasPhone.normalize(c))));';
	$tmp = tempnam(sys_get_temp_dir(), 'atlasphone') . '.js';
	file_put_contents($tmp, $script);
	$jsResults = json_decode((string) shell_exec('node ' . escapeshellarg($tmp)), true);
	@unlink($tmp);
	expect(is_array($jsResults), 'No se pudo ejecutar phone-country.js en Node');
	$i = 0;
	foreach ($phoneCases as $input => $expected) {
		expect($jsResults[$i] === $expected, "JS normalize('{$input}') = '{$jsResults[$i]}', esperado '{$expected}'");
		$i++;
	}
}

echo "CrmTeamsPhoneTest OK\n";
