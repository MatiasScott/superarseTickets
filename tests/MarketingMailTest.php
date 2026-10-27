<?php

// Run with: php tests\MarketingMailTest.php
define('APP_PATH', dirname(__DIR__) . '\app');
require APP_PATH . '\core\Controller.php';
require APP_PATH . '\controllers\CorreoController.php';
require APP_PATH . '\services\MailTicketPolicy.php';
require APP_PATH . '\services\MailboxService.php';
require APP_PATH . '\services\GraphClient.php';
require APP_PATH . '\services\GraphMailService.php';

function expect(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function env(string $key, mixed $default = null): mixed
{
	return $key === 'MAIL_TICKET_HISTORY_BOOTSTRAP_ENABLED' ? 'true' : $default;
}

class MarketingTestConnection extends PDO
{
	public array $groups = [
		['id' => 1, 'nombre' => 'Sin asignar'],
		['id' => 8, 'nombre' => 'Soporte tecnico'],
		['id' => 16, 'nombre' => 'Comunicación y Marketing'],
	];
	public string $deltaLink = '';

	public function __construct()
	{
	}

	public function exec(string $statement): int|false
	{
		expect(!str_contains($statement, 'history_bootstrap'), 'Marketing must never initialize historical bootstrap.');
		return 0;
	}

	public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
	{
		expect(str_contains($query, 'ticket_grupos'), 'Only group lookups are expected.');
		return new MarketingTestStatement($this, true);
	}

	public function prepare(string $query, array $options = []): PDOStatement|false
	{
		expect(str_contains($query, 'mail_sync_state'), 'Only delta state queries are expected.');
		return new MarketingTestStatement($this);
	}
}

class MarketingTestStatement extends PDOStatement
{
	public function __construct(private MarketingTestConnection $db, private bool $groups = false)
	{
	}

	public function execute(?array $params = null): bool
	{
		if (isset($params['delta_link'])) {
			$this->db->deltaLink = $params['delta_link'];
		}
		return true;
	}

	public function fetchColumn(int $column = 0): mixed
	{
		return $this->db->deltaLink;
	}

	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
	{
		return $this->groups ? $this->db->groups : [];
	}
}

class Database
{
	public static MarketingTestConnection $db;

	public static function getInstance(): self
	{
		return new self();
	}

	public function connection(): PDO
	{
		return self::$db;
	}
}

class MarketingTestGraphClient extends GraphClient
{
	public array $requests = [];
	public array $items = [];

	public function __construct()
	{
	}

	public function request(string $method, string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
	{
		$this->requests[] = ['path' => $path, 'query' => $query];
		return ['ok' => true, 'body' => ['value' => $this->items, '@odata.deltaLink' => 'https://example.test/delta']];
	}

	public function requestAbsolute(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
	{
		return $this->request($method, $url, $payload, [], $extraHeaders);
	}
}

Database::$db = new MarketingTestConnection();
$account = ['alias' => 'acc16', 'email' => 'solucionesnexo@superarse.edu.ec'];
$other = ['alias' => 'acc1', 'email' => 'soporte@superarse.edu.ec'];
expect(MailTicketPolicy::startDate($account) === '2026-10-27T05:00:00Z', 'Activation must be Ecuador midnight, not UTC midnight.');
expect(!MailTicketPolicy::acceptsDate($account, '2026-10-27T04:59:59Z'), 'One second before activation must be rejected.');
expect(MailTicketPolicy::acceptsDate($account, '2026-10-27T00:00:00-05:00'), 'Exactly midnight must be accepted.');
expect(MailTicketPolicy::acceptsDate($account, '2026-11-02T10:00:00Z'), 'The cutoff must remain fixed on future days.');
expect(MailTicketPolicy::acceptsDate($other, '2020-01-01T00:00:00Z'), 'Other mailboxes must keep their historical behavior.');
expect(MailTicketPolicy::startDate(['email' => ' SOLUCIONESNEXO@SUPERARSE.EDU.EC ']) !== null, 'Email matching must ignore case and spaces.');
try {
	MailTicketPolicy::acceptsDate($account, '');
	throw new LogicException('Missing received date was accepted.');
} catch (RuntimeException $e) {
	expect(str_contains($e->getMessage(), 'Fecha de recepcion invalida'), 'Invalid dates must fail explicitly.');
}

$controller = new CorreoController();
$guess = new ReflectionMethod(CorreoController::class, 'guessGroupIdFromEmail');
$marketingEmail = ['account_email' => $account['email'], 'subject' => 'Problema con factura y plataforma'];
expect($guess->invoke($controller, Database::$db, $marketingEmail, 1) === 16, 'The destination mailbox must override conflicting content.');
$marketingEmail['account_email'] = $other['email'];
$marketingEmail['subject'] = 'Diseño, fotos y videos';
expect($guess->invoke($controller, Database::$db, $marketingEmail, 1) === 8, 'Other direct mailbox mappings must retain precedence.');
$marketingEmail['account_email'] = 'sinmapa@example.test';
expect($guess->invoke($controller, Database::$db, $marketingEmail, 1) === 16, 'Marketing content must resolve to the new group.');
$detect = new ReflectionMethod(CorreoController::class, 'detectIntentFromText');
foreach (['artes', 'diseño', 'fotos', 'videos', 'marketing', 'comunicación institucional'] as $subject) {
	expect($detect->invoke($controller, $subject) === 'comunicacion_marketing', 'Marketing intent not recognized: ' . $subject);
}
expect($detect->invoke($controller, 'las partes del informe') === null, 'Artes must not match unrelated word fragments.');
Database::$db->groups = [];
try {
	$guess->invoke($controller, Database::$db, ['account_email' => $account['email']], 1);
	throw new LogicException('Missing group silently used the fallback.');
} catch (RuntimeException $e) {
	expect(str_contains($e->getMessage(), 'No existe el grupo activo'), 'A missing marketing group must fail explicitly.');
}

$graph = (new ReflectionClass(GraphMailService::class))->newInstanceWithoutConstructor();
$client = new MarketingTestGraphClient();
(new ReflectionProperty(GraphMailService::class, 'graphClient'))->setValue($graph, $client);
$old = ['id' => 'old', 'receivedDateTime' => '2026-10-27T04:59:59Z', 'subject' => 'Old'];
$current = ['id' => 'current', 'receivedDateTime' => '2026-10-27T05:00:00Z', 'subject' => 'Current'];
$client->items = [$old, $current];
$result = $graph->fetchDeltaForTicketing($account);
expect(count($result['emails']) === 1 && $result['emails'][0]['subject'] === 'Current', 'Delta must discard all pre-activation messages.');
expect($client->requests[0]['query']['$filter'] === 'receivedDateTime ge 2026-10-27T05:00:00Z', 'Initial delta must filter at the source.');
expect(Database::$db->deltaLink !== '', 'Delta progress must be persisted.');
$result = $graph->fetchDeltaForTicketing($account);
expect(count($result['emails']) === 1, 'An existing unfiltered delta cursor must still reject old messages.');
$result = $graph->fetchUnreadForTicketing($account);
expect(count($result['emails']) === 1, 'Unread import must also reject old messages.');
$request = end($client->requests);
expect($request['query']['$filter'] === 'isRead eq false and receivedDateTime ge 2026-10-27T05:00:00Z', 'Unread query must include the cutoff.');
$result = $graph->fetchSinceForTicketing($account, '2020-01-01T00:00:00Z');
$request = end($client->requests);
expect($request['query']['$filter'] === 'receivedDateTime ge 2026-10-27T05:00:00Z', 'Recovery must clamp the requested date.');
expect(count($result['emails']) === 1, 'Recovery must not return historical messages.');

$sync = new ReflectionMethod(CorreoController::class, 'syncEmailsIntoTickets');
$mailbox = (new ReflectionClass(MailboxService::class))->newInstanceWithoutConstructor();
$result = $sync->invoke($controller, Database::$db, $mailbox, [
	['account_email' => $account['email'], 'date' => $old['receivedDateTime']],
], []);
expect($result['created'] === 0 && $result['updated'] === 0 && $result['skipped'] === 1, 'Controller must reject historical mail before any database writes.');

echo "Marketing mail tests passed.\n";
