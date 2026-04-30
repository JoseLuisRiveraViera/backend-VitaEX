<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class SiestAuthService
{
	public function login(string $usuario, string $contrasena): array
	{
		$driver = Env::get('SIEST_AUTH_DRIVER', 'database') ?? 'database';

		if ($driver === 'database') {
			$localSiest = (new LocalSiestService())->login($usuario, $contrasena);
			if ($localSiest !== null) {
				return $localSiest;
			}

			throw new RuntimeException('Usuario no encontrado en el SIEst simulado local.');
		}

		if ($driver === 'mock' || filter_var(Env::get('SIEST_AUTH_MOCK', 'false'), FILTER_VALIDATE_BOOLEAN) === true) {
			return $this->mockLogin($usuario, $contrasena);
		}

		if ($driver === 'remote') {
			return $this->remoteLogin($usuario, $contrasena);
		}

		throw new RuntimeException('SIEST_AUTH_DRIVER inválido. Usa database, mock o remote.');
	}

	private function mockLogin(string $usuario, string $contrasena): array
	{
		$users = [
			'admin' => ['password' => 'admin123', 'role' => '22', 'cve_persona' => '90001'],
			'egresado' => ['password' => 'egresado123', 'role' => '40', 'cve_persona' => '12668'],
			'empresa' => ['password' => 'empresa123', 'role' => '41', 'cve_persona' => '20001'],
		];

		if (isset($users[$usuario]) === false || $users[$usuario]['password'] !== $contrasena) {
			throw new RuntimeException('Credenciales inválidas para modo local.');
		}

		$roleId = $users[$usuario]['role'];

		return [
			'sub' => $users[$usuario]['cve_persona'],
			'usuario' => $usuario,
			'perfil_id' => '1',
			'cve_persona' => $users[$usuario]['cve_persona'],
			'cve_division' => '3',
			'abreviatura_division' => 'DiNE',
			'roles' => [
				[
					'id' => $roleId,
					'nombre' => $this->roleName($roleId),
				],
			],
			'iat' => time(),
			'exp' => time() + 3600,
		];
	}

	private function remoteLogin(string $usuario, string $contrasena): array
	{
		$url = Env::get('SIEST_LOGIN_URL', 'https://www.utdelacosta.edu.mx/SIEstBackend/api/v1/login');
		$ch = curl_init($url);
		if ($ch === false) {
			throw new RuntimeException('No se pudo iniciar conexión con SIEst.');
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => json_encode([
				'usuario' => $usuario,
				'contrasena' => $contrasena,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			CURLOPT_TIMEOUT => 15,
		]);

		$response = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		if ($response === false || $status >= 400) {
			throw new RuntimeException('SIEst rechazó la autenticación. ' . $error);
		}

		$decoded = json_decode((string) $response, true);
		if (is_array($decoded) && isset($decoded['token']) && is_string($decoded['token'])) {
			$payload = $this->decodeJwtPayload($decoded['token']);
			if ($payload !== null) {
				return $payload;
			}
		}

		if (is_array($decoded) && isset($decoded['jwt']) && is_string($decoded['jwt'])) {
			$payload = $this->decodeJwtPayload($decoded['jwt']);
			if ($payload !== null) {
				return $payload;
			}
		}

		if (is_string($response) && substr_count($response, '.') === 2) {
			$payload = $this->decodeJwtPayload(trim($response));
			if ($payload !== null) {
				return $payload;
			}
		}

		if (is_array($decoded) === false) {
			throw new RuntimeException('Respuesta inválida del servicio SIEst.');
		}

		return $decoded;
	}

	private function decodeJwtPayload(string $jwt): ?array
	{
		$parts = explode('.', $jwt);
		if (count($parts) !== 3) {
			return null;
		}

		$json = base64_decode(strtr($parts[1], '-_', '+/'));
		$payload = json_decode($json ?: '', true);
		return is_array($payload) ? $payload : null;
	}

	private function roleName(string $roleId): string
	{
		return match ($roleId) {
			'1' => 'Administrador',
			'22' => 'Jefe vinculación / Admin UT',
			'41' => 'Empresa',
			default => 'Egresado',
		};
	}
}
