<?php
declare(strict_types=1);

namespace app\config;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
	private static ?PDO $connection = null;

	public static function connection(): PDO
	{
		if (self::$connection instanceof PDO) {
			return self::$connection;
		}

		$host = Env::get('DB_HOST', '127.0.0.1');
		$port = Env::get('DB_PORT', '5432');
		$name = Env::get('DB_NAME', Env::get('DB_DATABASE', ''));
		$user = Env::get('DB_USER', Env::get('DB_USERNAME', ''));
		$password = Env::get('DB_PASSWORD', '');
		$schema = Env::get('DB_SCHEMA', 'bolsa_trabajo');

		if ($name === '' || $user === '') {
			throw new RuntimeException('Base de datos no configurada. Revisa DB_NAME, DB_USER y DB_PASSWORD en .env.');
		}

		$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

		try {
			$pdo = new PDO($dsn, $user, $password, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
			$pdo->exec('SET search_path TO ' . self::quoteIdentifier($schema));
			self::$connection = $pdo;
			return self::$connection;
		} catch (PDOException $exception) {
			throw new RuntimeException('No se pudo conectar a PostgreSQL: ' . $exception->getMessage(), 0, $exception);
		}
	}

	private static function quoteIdentifier(string $identifier): string
	{
		return '"' . str_replace('"', '""', $identifier) . '"';
	}
}
