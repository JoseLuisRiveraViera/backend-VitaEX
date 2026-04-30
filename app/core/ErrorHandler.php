<?php
declare(strict_types=1);

namespace app\core;

use PDOException;
use Throwable;

class ErrorHandler
{
	/**
	 * @return array{message:string,status:int,errors:array<string,mixed>}
	 */
	public static function normalize(Throwable $exception): array
	{
		if ($exception instanceof PDOException) {
			return self::databaseError($exception);
		}

		$previous = $exception->getPrevious();
		if ($previous instanceof PDOException) {
			return self::databaseError($previous);
		}

		return [
			'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Error interno del servidor',
			'status' => 500,
			'errors' => [],
		];
	}

	/**
	 * @return array{message:string,status:int,errors:array<string,mixed>}
	 */
	private static function databaseError(PDOException $exception): array
	{
		$sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
		$message = self::cleanMessage($exception->getMessage());

		return match ($sqlState) {
			'23505' => [
				'message' => 'Registro duplicado',
				'status' => 409,
				'errors' => ['database' => $message],
			],
			'23503' => [
				'message' => 'Registro relacionado no existe',
				'status' => 422,
				'errors' => ['database' => $message],
			],
			'23514' => [
				'message' => 'Datos no cumplen restricción',
				'status' => 422,
				'errors' => ['database' => $message],
			],
			'P0001' => [
				'message' => $message,
				'status' => 422,
				'errors' => [],
			],
			default => [
				'message' => 'Error de base de datos',
				'status' => 500,
				'errors' => ['database' => $message],
			],
		};
	}

	private static function cleanMessage(string $message): string
	{
		$message = preg_replace('/^SQLSTATE\\[[^]]+\\]:\\s*/', '', $message) ?? $message;
		$message = preg_replace('/CONTEXT:.*/s', '', $message) ?? $message;
		return trim($message);
	}
}
