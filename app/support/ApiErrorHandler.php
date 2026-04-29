<?php
declare(strict_types=1);

namespace app\support;

use app\exceptions\HttpException;
use flight\Engine;
use flight\net\Route;
use Throwable;

class ApiErrorHandler
{
	public static function register(Engine $app): void
	{
		$app->map('error', function (Throwable $exception) use ($app): void {
			self::handle($app, $exception);
		});

		$app->map('notFound', function () use ($app): void {
			ApiResponse::error($app, 'Endpoint no encontrado', 404);
		});

		$app->map('methodNotFound', function (Route $route) use ($app): void {
			$allowedMethods = array_values(array_filter($route->methods, static fn(string $method): bool => $method !== 'OPTIONS'));
			$app->response()->header('Allow', implode(', ', $allowedMethods));

			ApiResponse::error($app, 'Metodo HTTP no permitido', 405, [
				'allowed_methods' => $allowedMethods,
			]);
		});
	}

	public static function handle(Engine $app, Throwable $exception): void
	{
		if ($exception instanceof HttpException) {
			ApiResponse::error(
				$app,
				$exception->getMessage(),
				$exception->getStatusCode(),
				$exception->getErrors()
			);
			return;
		}

		error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());

		$debug = [];
		if ($app->get('flight.debug') === true) {
			$debug = [
				'exception' => get_class($exception),
				'message' => $exception->getMessage(),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
			];
		}

		ApiResponse::error($app, 'Error interno del servidor', 500, [], $debug);
	}
}
