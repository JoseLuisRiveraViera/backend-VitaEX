<?php
declare(strict_types=1);

/**********************************************
 *            VitaEX App Config              *
 **********************************************
 *
 * Copy this file to app/config/config.php and keep real secrets out of git.
 */

$env = static function (string $key, $default = null) {
	$value = getenv($key);
	return $value === false ? $default : $value;
};

$appEnv = (string) $env('APP_ENV', 'local');
$appDebug = filter_var(
	$env('APP_DEBUG', in_array($appEnv, ['local', 'development', 'testing'], true) ? 'true' : 'false'),
	FILTER_VALIDATE_BOOLEAN
);

date_default_timezone_set((string) $env('APP_TIMEZONE', 'UTC'));
error_reporting(E_ALL);

if (function_exists('mb_internal_encoding') === true) {
	mb_internal_encoding('UTF-8');
}

if (function_exists('setlocale') === true) {
	setlocale(LC_ALL, 'en_US.UTF-8');
}

if (empty($app) === true) {
	$app = Flight::app();
}

if (defined('PROJECT_ROOT') === false) {
	define('PROJECT_ROOT', dirname(__DIR__, 2));
}

$app->path(PROJECT_ROOT);

$app->set('flight.base_url', '/');
$app->set('flight.case_sensitive', false);
$app->set('flight.log_errors', true);
$app->set('flight.handle_errors', true);
$app->set('flight.debug', $appDebug);
$app->set('flight.views.path', PROJECT_ROOT . '/app/views');
$app->set('flight.views.extension', '.php');
$app->set('flight.content_length', false);
$app->set('flight.allow_method_override', false);

$app->set('csp_nonce', bin2hex(random_bytes(16)));

return [
	'app' => [
		'name' => 'backend-VitaEX',
		'env' => $appEnv,
		'debug' => $appDebug,
		'tracy' => filter_var($env('APP_TRACY', 'false'), FILTER_VALIDATE_BOOLEAN),
		'url' => $env('APP_URL', 'http://localhost:8000'),
	],

	'database' => [
		// Supported by Eloquent: mysql, pgsql, sqlite, sqlsrv.
		'driver' => $env('DB_CONNECTION'),
		'host' => $env('DB_HOST', '127.0.0.1'),
		'port' => $env('DB_PORT', '3306'),
		'database' => $env('DB_DATABASE'),
		'username' => $env('DB_USERNAME'),
		'password' => $env('DB_PASSWORD'),
		'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
		'prefix' => '',
	],

	'security' => [
		'hsts' => $appEnv === 'production',
	],

	'runway' => [
		'index_root' => 'public/index.php',
		'app_root' => 'app/',
	],
];
