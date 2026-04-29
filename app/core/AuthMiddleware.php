<?php
declare(strict_types=1);

namespace app\core;

use app\services\JwtService;

class AuthMiddleware
{
	public static function requireAuth(): ?array
	{
		$token = Request::bearerToken();
		if ($token === null) {
			Response::error('Token no enviado', ['authorization' => 'Usa Authorization: Bearer <token>.'], 401);
			return null;
		}

		$payload = (new JwtService())->verify($token);
		if ($payload === null) {
			Response::error('Token inválido o expirado', [], 401);
			return null;
		}

		return $payload;
	}
}
