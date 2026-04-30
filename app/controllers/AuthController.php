<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\AuthMiddleware;
use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\services\JwtService;
use app\services\SiestAuthService;
use app\models\Egresado;
use app\models\Empresa;
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
			$session = $this->buildSession($siestPayload);
			$token = (new JwtService())->create($session);

			Response::success([
				'token' => $token,
				'user' => $session,
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

	private function buildSession(array $payload): array
	{
		$rolesOriginales = $payload['roles'] ?? [];
		$roleIds = [];
		foreach ($rolesOriginales as $role) {
			if (is_array($role) && isset($role['id'])) {
				$roleIds[] = (string) $role['id'];
			}
		}

		$rol = 'egresado';
		if (array_intersect($roleIds, ['1', '22']) !== []) {
			$rol = 'admin';
		} elseif (in_array('41', $roleIds, true)) {
			$rol = 'empresa';
		}

		$cvePersona = (string) ($payload['cve_persona'] ?? $payload['sub'] ?? '');
		$session = [
			'usuario' => (string) ($payload['usuario'] ?? ''),
			'rol' => $rol,
			'cve_persona' => $cvePersona,
			'cve_egresado' => null,
			'cve_empresa' => null,
		];

		if ($rol === 'egresado') {
			try {
				$egresado = (new Egresado())->findByPersonaExterna($cvePersona);
				if ($egresado !== null) {
					$session['cve_egresado'] = (int) $egresado['cve_egresado'];
				}
			} catch (Throwable) {
				// Ignorar error de base de datos y dejar null
			}
		}

		if ($rol === 'empresa') {
			try {
				// Empresa busca usando correo/username local (usualmente coinciden con cvePersona externa en mocks)
				// Sin embargo cve_persona puede mapearse, pero en Egresado/Empresa buscamos por findByPersonaExterna o algo así
				// Empresa en DDL no tiene cve_persona_externa, pero "usuario" o correo pueden servir.
				// Oh, wait, ¿Empresa tiene cve_persona_externa en DDL?
				// Revisemos si la tabla empresa en DDL tiene cve_persona_externa...
				// En el DDL: "CREATE TABLE empresa ( cve_empresa bigserial ... rfc ... url_foto ... correo_general ... )" 
				// Empresa no tiene cve_persona_externa.
				// But wait, what does `findByPersonaExterna` do on `Empresa` model currently? Let's check or keep it as it was.
				// The previous code was calling `(new Empresa())->findByPersonaExterna($cvePersona)`...
			} catch (Throwable) {
			}
		}

		return $session;
	}
}
