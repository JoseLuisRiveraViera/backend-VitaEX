<?php
declare(strict_types=1);

namespace app\support;

use flight\Engine;

class ApiResponse
{
	private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	/**
	 * @param mixed $data
	 * @param array<string, mixed> $meta
	 */
	public static function success(Engine $app, string $message, $data = null, int $status = 200, array $meta = []): void
	{
		$payload = [
			'success' => true,
			'message' => $message,
		];

		if ($data !== null) {
			$payload['data'] = $data;
		}

		if ($meta !== []) {
			$payload['meta'] = $meta;
		}

		self::send($app, $payload, $status);
	}

	/**
	 * @param array<string, mixed> $errors
	 * @param array<string, mixed> $debug
	 */
	public static function error(Engine $app, string $message, int $status = 400, array $errors = [], array $debug = []): void
	{
		$payload = [
			'success' => false,
			'message' => $message,
		];

		if ($errors !== []) {
			$payload['errors'] = $errors;
		}

		if ($debug !== []) {
			$payload['debug'] = $debug;
		}

		self::send($app, $payload, $status);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function send(Engine $app, array $payload, int $status): void
	{
		$app->response()->cache(0);
		$app->json($payload, $status, true, 'utf-8', self::JSON_OPTIONS);
	}
}
