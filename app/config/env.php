<?php
declare(strict_types=1);

namespace app\config;

class Env
{
	public static function load(string $path): void
	{
		if (is_file($path) === false || is_readable($path) === false) {
			return;
		}

		$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return;
		}

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#') || str_contains($line, '=') === false) {
				continue;
			}

			[$key, $value] = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);

			if ($key === '' || getenv($key) !== false) {
				continue;
			}

			if (
				(strlen($value) >= 2)
				&& (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
			) {
				$value = substr($value, 1, -1);
			}

			putenv($key . '=' . $value);
			$_ENV[$key] = $value;
			$_SERVER[$key] = $value;
		}
	}

	public static function get(string $key, ?string $default = null): ?string
	{
		$value = getenv($key);
		return $value === false ? $default : (string) $value;
	}
}
