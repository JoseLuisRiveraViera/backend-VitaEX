<?php
declare(strict_types=1);

namespace app\core;

use app\services\JwtService;

class AuthMiddleware
{
	private static ?array $user = null;

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

		self::$user = $payload;
		return $payload;
	}

	/**
	 * @param list<string> $roles
	 */
	public static function requireRole(array $roles): ?array
	{
		$user = self::requireAuth();
		if ($user === null) {
			return null;
		}

		if (in_array((string) ($user['rol'] ?? ''), $roles, true) === false) {
			Response::error('No tienes permisos para realizar esta acción', ['rol' => 'Rol no autorizado'], 403);
			return null;
		}

		return $user;
	}

	public static function getUser(): ?array
	{
		if (self::$user !== null) {
			return self::$user;
		}

		$token = Request::bearerToken();
		if ($token === null) {
			return null;
		}

		self::$user = (new JwtService())->verify($token);
		return self::$user;
	}
}
