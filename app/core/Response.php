<?php
declare(strict_types=1);

namespace app\core;

use Flight;
use Throwable;

class Response
{
	private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	/**
	 * @param mixed $data
	 */
	public static function success($data = [], string $message = 'Operación exitosa', int $status = 200): void
	{
		self::send([
			'success' => true,
			'message' => $message,
			'data' => $data ?? [],
		], $status);
	}

	/**
	 * @param array<string, mixed> $errors
	 */
	public static function error(string $message = 'Error', array $errors = [], int $status = 400): void
	{
		self::send([
			'success' => false,
			'message' => $message,
			'errors' => $errors,
		], $status);
	}

	public static function exception(Throwable $exception, string $fallback = 'Error interno del servidor'): void
	{
		$normalized = ErrorHandler::normalize($exception);
		$message = $normalized['message'] !== '' ? $normalized['message'] : $fallback;
		self::error($message, $normalized['errors'], $normalized['status']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function send(array $payload, int $status): void
	{
		Flight::response()->cache(0);
		Flight::json($payload, $status, true, 'utf-8', self::JSON_OPTIONS);
	}
}
