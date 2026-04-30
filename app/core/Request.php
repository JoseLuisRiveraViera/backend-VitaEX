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

	/**
	 * @return array<string, mixed>
	 */
	public static function form(): array
	{
		return $_POST;
	}

	/**
	 * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
	 */
	public static function file(string ...$names): ?array
	{
		$names = $names === [] ? ['file'] : $names;
		foreach ($names as $name) {
			$file = $_FILES[$name] ?? null;
			if (is_array($file) === false || is_array($file['name'] ?? null)) {
				continue;
			}

			return [
				'name' => (string) ($file['name'] ?? ''),
				'type' => (string) ($file['type'] ?? ''),
				'tmp_name' => (string) ($file['tmp_name'] ?? ''),
				'error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
				'size' => (int) ($file['size'] ?? 0),
			];
		}

		return null;
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
