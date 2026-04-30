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
use app\services\MailService;
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

			// Siempre requerir 2FA para todos los roles en esta versión segura
			$email = $session['correo'] ?? null;
			if (!$email) {
				// Si no tiene correo, no podemos enviar el código.
				// Para evitar bloqueos en desarrollo con seeds incompletos, podrías permitirlo,
				// pero la instrucción pide 2FA para todos.
				Response::error('El usuario no tiene un correo configurado para recibir el código de seguridad', [], 400);
				return;
			}

			$code = (string) random_int(100000, 999999);
			$mailed = (new MailService())->sendOtp($email, $code);

			if (!$mailed) {
				Response::error('No se pudo enviar el correo con el código de seguridad. Verifica la configuración SMTP.', [], 500);
				return;
			}

			// Creamos un token temporal (JWT) que contiene la sesión y el hash del código
			// Expira en 10 minutos (600s)
			$tempToken = (new JwtService())->create([
				'session' => $session,
				'otp_hash' => password_hash($code, PASSWORD_DEFAULT),
				'purpose' => '2fa_verification'
			], 600);

			Response::success([
				'requires_2fa' => true,
				'temp_session' => $tempToken, // Lo pasamos como temp_session para compatibilidad con el frontend
				'email_masked' => $this->maskEmail($email),
				'message' => 'Se ha enviado un código de seguridad a ' . $this->maskEmail($email)
			], 'Autenticación de dos factores requerida');
		} catch (Throwable $exception) {
			$code = ($exception instanceof \RuntimeException) ? 401 : 500;
			Response::error('Error al intentar iniciar sesión', [
				'mensaje' => $exception->getMessage(),
				'tipo' => get_class($exception)
			], $code);
		}
	}

	public function verify2fa(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['code', 'session']);
			if ($errors !== []) {
				Response::error('Datos invalidos', $errors, 422);
				return;
			}

			$code = (string) $body['code'];
			$tempToken = (string) $body['session'];

			$payload = (new JwtService())->verify($tempToken);
			if (!$payload || ($payload['purpose'] ?? '') !== '2fa_verification') {
				Response::error('La sesión de verificación ha expirado o es inválida. Por favor, intenta loguearte de nuevo.', [], 401);
				return;
			}

			if (!password_verify($code, $payload['otp_hash'])) {
				Response::error('Código incorrecto', ['code' => 'El código de seguridad ingresado no es válido.'], 401);
				return;
			}

			$session = $payload['session'];
			$token = (new JwtService())->create($session);

			Response::success([
				'token' => $token,
				'user' => $session,
			], 'Autenticación completada');
		} catch (Throwable $exception) {
			Response::error('Error en verificación', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function resendCode(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['session']);
			if ($errors !== []) {
				Response::error('Datos invalidos', $errors, 422);
				return;
			}

			$tempToken = (string) $body['session'];
			$payload = (new JwtService())->verify($tempToken);

			if (!$payload || ($payload['purpose'] ?? '') !== '2fa_verification') {
				Response::error('La sesión ha expirado. Por favor, inicia sesión de nuevo.', [], 401);
				return;
			}

			$session = $payload['session'];
			$email = $session['correo'] ?? null;

			if (!$email) {
				Response::error('No se pudo encontrar el correo para reenvío', [], 400);
				return;
			}

			$code = (string) random_int(100000, 999999);
			$mailed = (new MailService())->sendOtp($email, $code);

			if (!$mailed) {
				Response::error('No se pudo enviar el correo.', [], 500);
				return;
			}

			$newTempToken = (new JwtService())->create([
				'session' => $session,
				'otp_hash' => password_hash($code, PASSWORD_DEFAULT),
				'purpose' => '2fa_verification'
			], 600);

			Response::success([
				'temp_session' => $newTempToken,
				'message' => 'Se ha reenviado un nuevo código a ' . $this->maskEmail($email)
			], 'Código reenviado');
		} catch (Throwable $e) {
			Response::error('Error al reenviar código', ['detail' => $e->getMessage()], 500);
		}
	}

	public function forgotPassword(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['usuario']);
			if ($errors !== []) {
				Response::error('El usuario o correo es requerido', $errors, 422);
				return;
			}

			$identifier = trim((string) $body['usuario']);
			
			// Buscamos al usuario en las 3 tablas posibles
			$egresado = (new Egresado())->findByLoginIdentifier($identifier);
			$empresa = (new Empresa())->findByLoginIdentifier($identifier);
			$admin = (new AdministradorUt())->findByLoginIdentifier($identifier);

			$userFound = $egresado ?: ($empresa ?: $admin);
			
			if (!$userFound) {
				// Por seguridad, no decimos si el usuario existe o no
				Response::success(['message' => 'Si el usuario existe y tiene un correo configurado, recibirá un código de recuperación.'], 'Solicitud procesada');
				return;
			}

			// Intentamos obtener el correo
			$email = $userFound['correo_institucional'] ?? ($userFound['correo_personal'] ?? ($userFound['correo_general'] ?? null));

			if (!$email) {
				Response::success(['message' => 'Si el usuario existe y tiene un correo configurado, recibirá un código de recuperación.'], 'Solicitud procesada');
				return;
			}

			$code = (string) random_int(100000, 999999);
			$mailed = (new MailService())->sendResetCode($email, $code);

			if (!$mailed) {
				Response::error('Error al enviar el correo de recuperación.', [], 500);
				return;
			}

			// Guardamos el estado en un token temporal
			$tempToken = (new JwtService())->create([
				'email' => $email,
				'otp_hash' => password_hash($code, PASSWORD_DEFAULT),
				'user_type' => $egresado ? 'egresado' : ($empresa ? 'empresa' : 'admin'),
				'user_id' => $userFound['cve_egresado'] ?? ($userFound['cve_empresa'] ?? $userFound['cve_administrador_ut']),
				'purpose' => 'password_recovery'
			], 900); // 15 minutos

			Response::success([
				'session' => $tempToken,
				'email_masked' => $this->maskEmail($email),
				'message' => 'Se ha enviado un código de recuperación a ' . $this->maskEmail($email)
			], 'Código de recuperación enviado');
		} catch (Throwable $e) {
			Response::error('Error en proceso de recuperación', ['detail' => $e->getMessage()], 500);
		}
	}

	public function verifyResetCode(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['code', 'session']);
			if ($errors !== []) {
				Response::error('Datos invalidos', $errors, 422);
				return;
			}

			$payload = (new JwtService())->verify((string)$body['session']);
			if (!$payload || ($payload['purpose'] ?? '') !== 'password_recovery') {
				Response::error('La sesión ha expirado o es inválida.', [], 401);
				return;
			}

			if (!password_verify((string)$body['code'], $payload['otp_hash'])) {
				Response::error('Código incorrecto', [], 401);
				return;
			}

			// Generamos un token que autoriza el cambio de contraseña
			$finalToken = (new JwtService())->create([
				'user_type' => $payload['user_type'],
				'user_id' => $payload['user_id'],
				'purpose' => 'password_reset_authorized'
			], 300); // 5 minutos para cambiarla

			Response::success([
				'reset_token' => $finalToken
			], 'Código verificado correctamente');
		} catch (Throwable $e) {
			Response::error('Error al verificar código', ['detail' => $e->getMessage()], 500);
		}
	}

	public function resetPassword(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['password', 'token']);
			if ($errors !== []) {
				Response::error('La nueva contraseña y el token son requeridos', $errors, 422);
				return;
			}

			$payload = (new JwtService())->verify((string)$body['token']);
			if (!$payload || ($payload['purpose'] ?? '') !== 'password_reset_authorized') {
				Response::error('La sesión ha expirado o no está autorizada para cambiar la contraseña.', [], 401);
				return;
			}

			$userType = $payload['user_type'];
			$userId = $payload['user_id'];
			$newPass = password_hash((string)$body['password'], PASSWORD_DEFAULT);

			// Actualizamos en la tabla correspondiente
			// Nota: No usamos SIEstAuthService porque el cambio es local a la Bolsa de Trabajo
			// si el usuario no existe en SIEst o si queremos permitir password local.
			if ($userType === 'egresado') {
				(new Egresado())->where('cve_egresado', $userId)->update(['password' => $newPass]);
			} elseif ($userType === 'empresa') {
				(new Empresa())->where('cve_empresa', $userId)->update(['password' => $newPass]);
			} elseif ($userType === 'admin') {
				(new AdministradorUt())->where('cve_administrador_ut', $userId)->update(['password' => $newPass]);
			}

			Response::success([], 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
		} catch (Throwable $e) {
			Response::error('Error al restablecer contraseña', ['detail' => $e->getMessage()], 500);
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
			$cveRol = $this->roleIdForSession($rol, $payload, $roleIds);

			$session = [
				'cve_persona' => $cvePersona,
				'usuario' => $this->firstString($payload, ['usuario', 'nombre_usuario', 'username', 'email', 'correo']) ?: $loginIdentifier,
				'rol' => $rol,
				'roles_originales' => $rolesOriginales,
				'perfil_id' => $payload['perfil_id'] ?? $cveRol,
				'cve_rol' => $cveRol,
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

	private function roleIdForSession(string $rol, array $payload, array $roleIds): string
	{
		foreach ([$payload['cve_rol'] ?? null, $payload['perfil_id'] ?? null, ...$roleIds] as $value) {
			$id = $this->roleIdFromValue($value);
			if ($id !== null && $this->roleIdMatchesResolvedRole($id, $rol)) {
				return $id;
			}
		}

		return match ($rol) {
			'empresa' => '41',
			'admin' => '22',
			default => '40',
		};
	}

	private function roleIdMatchesResolvedRole(string $id, string $rol): bool
	{
		return match ($rol) {
			'empresa' => $id === '41',
			'admin' => in_array($id, ['1', '22'], true),
			default => $id === '40',
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

	private function maskEmail(string $email): string
	{
		$parts = explode('@', $email);
		if (count($parts) !== 2) return $email;
		$name = $parts[0];
		$domain = $parts[1];
		$len = strlen($name);
		if ($len <= 2) return $name . '@' . $domain;
		$visible = (int) ceil($len / 2);
		return substr($name, 0, $visible) . str_repeat('*', $len - $visible) . '@' . $domain;
	}
}
