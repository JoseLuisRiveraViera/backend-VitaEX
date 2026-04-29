<?php
declare(strict_types=1);

use app\services\UserService;
use app\support\ApiErrorHandler;
use app\validators\UserValidator;
use flight\debug\tracy\TracyExtensionLoader;
use flight\Engine;
use Illuminate\Database\Capsule\Manager as Capsule;
use Rakit\Validation\Validator;
use Tracy\Debugger;

/**
 * @var array<string, mixed> $config
 * @var Engine $app
 * @var string $ds
 */

if (function_exists('vitaex_normalize_database_config') === false) {
	/**
	 * @param array<string, mixed> $database
	 * @return array<string, mixed>
	 */
	function vitaex_normalize_database_config(array $database): array
	{
		if (isset($database['dbname']) && empty($database['database'])) {
			$database['database'] = $database['dbname'];
		}

		if (isset($database['user']) && empty($database['username'])) {
			$database['username'] = $database['user'];
		}

		if (isset($database['file_path']) && empty($database['database'])) {
			$database['driver'] = 'sqlite';
			$database['database'] = $database['file_path'];
		}

		$driver = $database['driver'] ?? null;
		if ($driver === null || $driver === '') {
			return [];
		}

		if ($driver === 'sqlite') {
			return [
				'driver' => 'sqlite',
				'database' => $database['database'] ?? '',
				'prefix' => $database['prefix'] ?? '',
				'foreign_key_constraints' => true,
			];
		}

		return array_filter([
			'driver' => $driver,
			'host' => $database['host'] ?? '127.0.0.1',
			'port' => $database['port'] ?? null,
			'database' => $database['database'] ?? null,
			'username' => $database['username'] ?? null,
			'password' => $database['password'] ?? null,
			'charset' => $database['charset'] ?? 'utf8mb4',
			'collation' => $database['collation'] ?? 'utf8mb4_unicode_ci',
			'prefix' => $database['prefix'] ?? '',
			'strict' => true,
		], static fn($value): bool => $value !== null);
	}
}

if (function_exists('vitaex_database_is_configured') === false) {
	/**
	 * @param array<string, mixed> $database
	 */
	function vitaex_database_is_configured(array $database): bool
	{
		if (empty($database['driver']) || empty($database['database'])) {
			return false;
		}

		if ($database['driver'] === 'sqlite') {
			return is_string($database['database']) && $database['database'] !== '';
		}

		return empty($database['host']) === false && array_key_exists('username', $database);
	}
}

$appConfig = $config['app'] ?? [];
$environment = (string) ($appConfig['env'] ?? getenv('APP_ENV') ?: 'local');
$debug = (bool) ($appConfig['debug'] ?? in_array($environment, ['local', 'development', 'testing'], true));
$tracyEnabled = (bool) ($appConfig['tracy'] ?? false);

$app->set('app.name', $appConfig['name'] ?? 'backend-VitaEX');
$app->set('app.env', $environment);
$app->set('flight.debug', $debug);

Debugger::$logDirectory = __DIR__ . $ds . '..' . $ds . 'log';
Debugger::$strictMode = true;
Debugger::$showBar = false;

if ($tracyEnabled === true) {
	Debugger::enable($debug ? Debugger::Development : Debugger::Production);
	Debugger::$showBar = $debug && php_sapi_name() !== 'cli';

	if (Debugger::$showBar === true) {
		(new TracyExtensionLoader($app));
	}
}

$app->set('database.enabled', false);
$app->set('database.connection', null);

$databaseConfig = vitaex_normalize_database_config($config['database'] ?? []);
if (vitaex_database_is_configured($databaseConfig) === true) {
	$capsule = new Capsule();
	$capsule->addConnection($databaseConfig);
	$capsule->setAsGlobal();
	$capsule->bootEloquent();

	$app->set('database.enabled', true);
	$app->set('database.connection', $databaseConfig['driver']);
}

$app->register('validator', Validator::class);
$app->register('userValidator', UserValidator::class, [$app->validator()]);
$app->register('userService', UserService::class, [
	$app->userValidator(),
	$app->get('database.enabled') === true,
]);

ApiErrorHandler::register($app);
