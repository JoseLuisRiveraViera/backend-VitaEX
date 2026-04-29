<?php
declare(strict_types=1);

namespace app\core;

use Flight;

class Request
{
	/**
	 * @return array<string, mixed>
	 */
	public static function body(): array
	{
		$data = Flight::request()->data->getData();
		return is_array($data) ? $data : [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function query(): array
	{
		$data = Flight::request()->query->getData();
		return is_array($data) ? $data : [];
	}

	public static function bearerToken(): ?string
	{
		$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
		if (preg_match('/Bearer\s+(.+)/i', $header, $matches) !== 1) {
			return null;
		}

		return trim($matches[1]);
	}
}
