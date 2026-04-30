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
			'cve_persona' => $cvePersona,
			'usuario' => (string) ($payload['usuario'] ?? ''),
			'rol' => $rol,
			'roles_originales' => $rolesOriginales,
			'perfil_id' => $payload['perfil_id'] ?? null,
		];

		if ($rol === 'egresado') {
			$egresado = null;
			try {
				$egresado = (new Egresado())->findByPersonaExterna($cvePersona);
			} catch (Throwable) {
				$session['registro_local'] = 'No se pudo consultar el registro local del egresado';
			}
			if ($egresado !== null) {
				$session['cve_egresado'] = $egresado['cve_egresado'];
			} elseif (isset($session['registro_local']) === false) {
				$session['registro_local'] = 'El egresado aún no tiene registro local en la Bolsa de Trabajo';
			}
		}

		if ($rol === 'empresa') {
			$empresa = null;
			try {
				$empresa = (new Empresa())->findByPersonaExterna($cvePersona);
			} catch (Throwable) {
				$session['registro_local'] = 'No se pudo consultar el registro local de la empresa';
			}
			if ($empresa !== null) {
				$session['cve_empresa'] = $empresa['cve_empresa'];
			} elseif (isset($session['registro_local']) === false) {
				$session['registro_local'] = 'La empresa aún no tiene registro local en la Bolsa de Trabajo';
			}
		}

		return $session;
	}
}
