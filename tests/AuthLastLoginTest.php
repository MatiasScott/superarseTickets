<?php

// Run with: php tests\AuthLastLoginTest.php
class LoginTestConnection extends PDO
{
	public array $users = [];
	public array $updates = [];
	public bool $failUpdate = false;

	public function __construct()
	{
	}

	public function prepare(string $query, array $options = []): PDOStatement|false
	{
		return new LoginTestStatement($this, $query);
	}
}

class LoginTestStatement extends PDOStatement
{
	private array|false $result = false;

	public function __construct(private LoginTestConnection $connection, private string $query)
	{
	}

	public function execute(?array $params = null): bool
	{
		if (str_starts_with($this->query, 'SELECT u.*')) {
			foreach ($this->connection->users as $user) {
				if ($user['email'] === $params['credential'] || $user['nombre'] === $params['credential']) {
					$this->result = $user;
					break;
				}
			}
			return true;
		}

		expect(
			$this->query === 'UPDATE usuarios SET last_login_at = NOW() WHERE id = :id',
			'The update must use the configured database, NOW() and a bound user ID.'
		);
		expect(!Auth::check(), 'The access must be recorded before establishing the session.');
		if ($this->connection->failUpdate) {
			throw new PDOException('Simulated last_login_at update failure');
		}
		$this->connection->updates[] = $params['id'];
		$this->connection->users[$params['id']]['last_login_at'] = '2026-10-02 10:56:24';
		return true;
	}

	public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
	{
		return $this->result;
	}
}

class Database
{
	public static LoginTestConnection $db;

	public static function getInstance(): self
	{
		return new self();
	}

	public function connection(): PDO
	{
		return self::$db;
	}
}

function expect(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function e(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path): string
{
	return $path;
}

function get_flash(string $key): ?string
{
	return null;
}

function csrf_field(): string
{
	return '';
}

require __DIR__ . '\..\app\core\Model.php';
require __DIR__ . '\..\app\models\Usuario.php';
require __DIR__ . '\..\app\core\Auth.php';

$_SESSION = [];
Database::$db = new LoginTestConnection();
Database::$db->users = [
	7 => [
		'id' => 7,
		'nombre' => 'Test User',
		'email' => 'test@example.test',
		'password' => password_hash('Test-password-123!', PASSWORD_BCRYPT),
		'rol_id' => 2,
		'rol_nombre' => 'Agente',
		'must_change_password' => 1,
		'last_login_at' => null,
	],
	8 => [
		'id' => 8,
		'nombre' => 'Legacy User',
		'email' => 'legacy@example.test',
		'password' => 'legacy-password',
		'last_login_at' => null,
	],
];

expect(!Auth::attempt('missing@example.test', 'Test-password-123!'), 'Unknown users must not log in.');
expect(!Auth::attempt('test@example.test', 'incorrect'), 'Wrong passwords must not log in.');
expect(Database::$db->updates === [] && !Auth::check(), 'Failed credentials must not record an access or create a session.');

expect(Auth::attempt('test@example.test', 'Test-password-123!'), 'Valid hashed credentials must log in.');
expect(Database::$db->updates === [7], 'Only the authenticated user must be updated.');
expect(Database::$db->users[8]['last_login_at'] === null, 'Other users must keep their original access date.');
expect(Auth::id() === 7 && Auth::user()['rol_id'] === 2 && Auth::user()['must_change_password'], 'Session data must be preserved.');
Auth::logout();
expect(Database::$db->updates === [7], 'Logout must not record an access.');

expect(Auth::attempt('Test User', 'Test-password-123!'), 'Login by name must also record the access.');
expect(Database::$db->updates === [7, 7], 'Every successful login must refresh the access.');
Auth::logout();
expect(Auth::attempt('legacy@example.test', 'legacy-password'), 'Existing legacy password behavior must be preserved.');
expect(Database::$db->updates === [7, 7, 8], 'Legacy login must update the correct user.');
Auth::logout();

Database::$db->failUpdate = true;
$errorLog = tempnam(sys_get_temp_dir(), 'login-test-');
if ($errorLog === false) {
	throw new RuntimeException('Could not create the test error log.');
}
$originalErrorLog = ini_get('error_log');
ini_set('error_log', $errorLog);
try {
	expect(!Auth::attempt('test@example.test', 'Test-password-123!'), 'Database errors must fail the login.');
	expect(!Auth::check(), 'A failed update must not establish a session.');
	expect(Database::$db->updates === [7, 7, 8], 'A failed update must not record an access.');
	expect(str_contains(file_get_contents($errorLog), 'Simulated last_login_at update failure'), 'Database errors must be logged.');
} finally {
	ini_set('error_log', $originalErrorLog);
	unlink($errorLog);
}

function renderUsersView(string $view, array $usuario, array $usuarios): string
{
	ob_start();
	try {
		require __DIR__ . '\..\app\views\usuarios\\' . $view . '.php';
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
}

foreach (['index', 'show'] as $view) {
	$user = Database::$db->users[7];
	$html = renderUsersView($view, $user, [$user]);
	expect(str_contains($html, '02/10/2026 10:56:24'), 'The ' . $view . ' view must display the last login with seconds.');
	$user['last_login_at'] = null;
	$html = renderUsersView($view, $user, [$user]);
	expect(str_contains($html, 'Sin accesos registrados'), 'The ' . $view . ' view must handle users without a recorded login.');
}
expect(str_contains(renderUsersView('index', [], []), 'colspan="7"'), 'The empty table must span all seven columns.');

echo "PASS: successful and failed logins, user isolation, session data, error logging and last-login views.\n";
