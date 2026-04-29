<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\AuthMiddleware;
use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\services\JwtService;
use app\services\SiestAuthService;
use Throwable;

class AuthController
{
	public function login(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['usuario', 'contrasena']);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			$siestPayload = (new SiestAuthService())->login((string) $body['usuario'], (string) $body['contrasena']);
			$token = (new JwtService())->create($siestPayload);

			Response::success([
				'token' => $token,
				'user' => $siestPayload,
			], 'Login correcto');
		} catch (Throwable $exception) {
			Response::error('No se pudo iniciar sesión', ['detail' => $exception->getMessage()], 401);
		}
	}

	public function me(): void
	{
		try {
			$payload = AuthMiddleware::requireAuth();
			if ($payload === null) {
				return;
			}

			Response::success($payload, 'Usuario autenticado');
		} catch (Throwable $exception) {
			Response::error('No se pudo obtener el usuario autenticado', ['detail' => $exception->getMessage()], 500);
		}
	}
}
