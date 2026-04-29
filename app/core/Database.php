<?php

class Database
{
	private static ?self $instance = null;
	private PDO $connection;

	private function __construct()
	{
		$host = db_config('host', 'localhost');
		$port = db_config('port', '3306');
		$database = db_config('database', 'istsTicket');
		$username = db_config('username', 'root');
		$password = db_config('password', '');
		$charset = db_config('charset', 'utf8mb4');

		$dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

		$this->connection = new PDO($dsn, $username, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}

	public static function getInstance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function connection(): PDO
	{
		return $this->connection;
	}
}
