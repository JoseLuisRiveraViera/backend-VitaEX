<?php
declare(strict_types=1);

namespace app\config;

class Cors
{
	public static function apply(): void
	{
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
		$defaultOrigin = Env::get('FRONTEND_URL', 'http://localhost:4200') ?? 'http://localhost:4200';
		$allowedOrigins = array_filter(array_map('trim', explode(',', Env::get('CORS_ALLOWED_ORIGINS', $defaultOrigin) ?? '')));

		if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
			header('Access-Control-Allow-Origin: ' . $origin);
		} elseif ($allowedOrigins !== []) {
			header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
		}

		header('Vary: Origin');
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
		header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
		header('Access-Control-Max-Age: 86400');

		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
			http_response_code(204);
			exit;
		}
	}
}
