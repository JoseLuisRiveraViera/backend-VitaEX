<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\AuthMiddleware;
use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\models\AdministradorUt;
use app\models\Egresado;
use app\models\Empresa;
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
				Response::error('Datos invalidos', $errors, 422);
				return;
			}

			$usuario = trim((string) $body['usuario']);
			if ($usuario === '') {
				Response::error('Datos invalidos', ['usuario' => 'El usuario, matricula o correo es requerido.'], 422);
				return;
			}

			$siestPayload = (new SiestAuthService())->login($usuario, (string) $body['contrasena']);
			$session = $this->buildSession($siestPayload, $usuario);
			$token = (new JwtService())->create($session);

			Response::success([
				'token' => $token,
				'user' => $session,
			], 'Login correcto');
		} catch (Throwable $exception) {
			Response::error('No se pudo iniciar sesion', ['detail' => $exception->getMessage()], 401);
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

	private function buildSession(array $payload, string $loginIdentifier): array
	{
		$loginIdentifier = trim((string) ($payload['login_identifier'] ?? $loginIdentifier));
		$rolesOriginales = $this->normalizeRoles($payload);
		$roleIds = $this->extractRoleIds($payload, $rolesOriginales);
		$cvePersona = $this->firstString($payload, ['cve_persona', 'cve_persona_externa', 'sub', 'id_persona', 'persona_id']);

		$egresado = $this->resolveEgresado($cvePersona, $loginIdentifier, $payload);
		$empresa = $this->resolveEmpresa($cvePersona, $loginIdentifier, $payload);
		$admin = $this->resolveAdministrador($cvePersona, $loginIdentifier, $payload);
		$rol = $this->resolveRole($payload, $roleIds, $egresado, $empresa, $admin);

		$session = [
			'cve_persona' => $cvePersona,
			'usuario' => $this->firstString($payload, ['usuario', 'nombre_usuario', 'username', 'email', 'correo']) ?: $loginIdentifier,
			'rol' => $rol,
			'roles_originales' => $rolesOriginales,
			'perfil_id' => $payload['perfil_id'] ?? ($payload['cve_rol'] ?? null),
		];

		if ($loginIdentifier !== '') {
			$session['login_identifier'] = $loginIdentifier;
		}
		if (!empty($payload['origen'])) {
			$session['origen'] = $payload['origen'];
		}

		if ($rol === 'egresado') {
			if ($egresado !== null) {
				$session['cve_egresado'] = $egresado['cve_egresado'];
				$session['matricula'] = $egresado['matricula'] ?? null;
				$session['nombre'] = $this->nombrePersona($egresado);
				$session['correo'] = $egresado['correo_institucional'] ?? ($egresado['correo_personal'] ?? null);
				$session['foto_url'] = $egresado['url_foto'] ?? null;
				$session['cv_url'] = $egresado['url_cv'] ?? null;
				$session['cve_persona'] = $session['cve_persona'] ?: (string) ($egresado['cve_persona_externa'] ?? '');
			} else {
				$session['registro_local'] = 'El egresado aun no tiene registro local en la Bolsa de Trabajo';
			}
		}

		if ($rol === 'empresa') {
			if ($empresa !== null) {
				$session['cve_empresa'] = $empresa['cve_empresa'];
				$session['nombre'] = $empresa['nombre_comercial'] ?: $empresa['razon_social'];
				$session['correo'] = $empresa['correo_general'] ?? null;
				$session['foto_url'] = $empresa['url_foto'] ?? null;
				$session['logo_url'] = $empresa['url_foto'] ?? null;
			} else {
				$session['registro_local'] = 'La empresa aun no tiene registro local en la Bolsa de Trabajo';
			}
		}

		if ($rol === 'admin') {
			if ($admin !== null) {
				$session['cve_administrador_ut'] = $admin['cve_administrador_ut'];
				$session['nombre'] = $this->nombrePersona($admin);
				$session['correo'] = $admin['correo_institucional'] ?? null;
				$session['cve_persona'] = $session['cve_persona'] ?: (string) ($admin['cve_persona_externa'] ?? '');
			}
		}

		return $session;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function normalizeRoles(array $payload): array
	{
		$roles = $payload['roles_originales'] ?? ($payload['roles'] ?? []);
		if (is_array($roles) === false) {
			$roles = [$roles];
		}

		$normalized = [];
		foreach ($roles as $role) {
			if (is_array($role)) {
				$id = $this->roleIdFromValue($role['id'] ?? ($role['cve_rol'] ?? ($role['clave'] ?? ($role['clave_rol'] ?? ($role['rol'] ?? ($role['nombre'] ?? ''))))));
				if ($id !== null) {
					$role['id'] = $id;
				}
				$normalized[] = $role;
				continue;
			}

			$id = $this->roleIdFromValue($role);
			$normalized[] = [
				'id' => $id ?? (string) $role,
				'nombre' => (string) $role,
			];
		}

		return $normalized;
	}

	/**
	 * @param list<array<string,mixed>> $rolesOriginales
	 * @return list<string>
	 */
	private function extractRoleIds(array $payload, array $rolesOriginales): array
	{
		$ids = [];
		foreach ($rolesOriginales as $role) {
			foreach (['id', 'cve_rol', 'clave', 'clave_rol', 'rol', 'nombre'] as $key) {
				if (isset($role[$key])) {
					$id = $this->roleIdFromValue($role[$key]);
					if ($id !== null) {
						$ids[] = $id;
					}
				}
			}
		}

		foreach (['rol', 'role', 'tipo', 'cve_rol', 'clave_rol'] as $key) {
			if (isset($payload[$key])) {
				$id = $this->roleIdFromValue($payload[$key]);
				if ($id !== null) {
					$ids[] = $id;
				}
			}
		}

		return array_values(array_unique($ids));
	}

	private function resolveRole(array $payload, array $roleIds, ?array $egresado, ?array $empresa, ?array $admin): string
	{
		if (array_intersect($roleIds, ['1', '22']) !== []) {
			return 'admin';
		}
		if (in_array('41', $roleIds, true)) {
			return 'empresa';
		}
		if (in_array('40', $roleIds, true)) {
			return 'egresado';
		}

		$explicit = $this->roleIdFromValue($this->firstString($payload, ['rol', 'role', 'tipo']));
		if (in_array($explicit, ['1', '22'], true)) {
			return 'admin';
		}
		if ($explicit === '41') {
			return 'empresa';
		}
		if ($explicit === '40') {
			return 'egresado';
		}

		if ($admin !== null) {
			return 'admin';
		}
		if ($empresa !== null) {
			return 'empresa';
		}
		if ($egresado !== null) {
			return 'egresado';
		}

		return 'egresado';
	}

	private function roleIdFromValue(mixed $value): ?string
	{
		if (is_scalar($value) === false) {
			return null;
		}

		$raw = strtoupper(trim((string) $value));
		if ($raw === '') {
			return null;
		}
		if (ctype_digit($raw)) {
			return $raw;
		}

		return match ($raw) {
			'ADMIN', 'ADMINISTRADOR', 'ADMINISTRADOR_UT', 'JEFE_VINCULACION' => '22',
			'EMPRESA', 'COMPANY', 'EMPLEADOR' => '41',
			'EGRESADO', 'ALUMNO', 'ESTUDIANTE', 'CANDIDATO' => '40',
			default => null,
		};
	}

	private function resolveEgresado(string $cvePersona, string $loginIdentifier, array $payload): ?array
	{
		try {
			$model = new Egresado();
			if ($cvePersona !== '') {
				$row = $model->findByPersonaExterna($cvePersona);
				if ($row !== null) {
					return $row;
				}
			}

			foreach ($this->identifierCandidates($loginIdentifier, $payload) as $identifier) {
				$row = $model->findByLoginIdentifier($identifier);
				if ($row !== null) {
					return $row;
				}
			}
		} catch (Throwable) {
			return null;
		}

		return null;
	}

	private function resolveEmpresa(string $cvePersona, string $loginIdentifier, array $payload): ?array
	{
		try {
			$model = new Empresa();
			if ($cvePersona !== '') {
				$row = $model->findByPersonaExterna($cvePersona);
				if ($row !== null) {
					return $row;
				}
			}

			foreach ($this->identifierCandidates($loginIdentifier, $payload) as $identifier) {
				$row = $model->findByLoginIdentifier($identifier);
				if ($row !== null) {
					return $row;
				}
			}
		} catch (Throwable) {
			return null;
		}

		return null;
	}

	private function resolveAdministrador(string $cvePersona, string $loginIdentifier, array $payload): ?array
	{
		try {
			$model = new AdministradorUt();
			if ($cvePersona !== '') {
				$row = $model->findByPersonaExterna($cvePersona);
				if ($row !== null) {
					return $row;
				}
			}

			foreach ($this->identifierCandidates($loginIdentifier, $payload) as $identifier) {
				$row = $model->findByLoginIdentifier($identifier);
				if ($row !== null) {
					return $row;
				}
			}
		} catch (Throwable) {
			return null;
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	private function identifierCandidates(string $loginIdentifier, array $payload): array
	{
		$candidates = [];
		foreach ([$loginIdentifier, $this->firstString($payload, ['matricula', 'correo', 'email', 'correo_institucional', 'correo_personal', 'contacto_email'])] as $value) {
			$value = trim((string) $value);
			if ($value !== '') {
				$candidates[] = $value;
			}
		}

		return array_values(array_unique($candidates));
	}

	/**
	 * @param list<string> $keys
	 */
	private function firstString(array $payload, array $keys): string
	{
		foreach ($keys as $key) {
			if (isset($payload[$key]) && is_scalar($payload[$key]) && trim((string) $payload[$key]) !== '') {
				return trim((string) $payload[$key]);
			}
		}

		return '';
	}

	private function nombrePersona(array $row): string
	{
		return trim(implode(' ', array_filter([
			$row['nombre'] ?? null,
			$row['primer_apellido'] ?? null,
			$row['segundo_apellido'] ?? null,
		], static fn(mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')));
	}
}
