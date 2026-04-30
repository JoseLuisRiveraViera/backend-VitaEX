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

		$content = file_get_contents($path);
		if ($content === false) {
			return;
		}

		// Regex to match KEY=VALUE where VALUE can be quoted and multi-line
		// Matches: KEY = "value" or KEY = 'value' or KEY = value
		// Modifier 's' allows . to match newlines
		preg_match_all('/^\s*([A-Z0-9_]+)\s*=\s*(?:(["\'])(.*?)\2|([^#\r\n]*))/ms', $content, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$key = $match[1];
			$value = $match[3] !== '' ? $match[3] : trim($match[4] ?? '');

			if ($key === '' || getenv($key) !== false) {
				continue;
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
