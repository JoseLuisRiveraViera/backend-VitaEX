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

		if ($driver === 'remote') {
			$isEmail = filter_var($usuario, FILTER_VALIDATE_EMAIL);
			
			if ($isEmail !== false) {
				$localSiest = (new LocalSiestService())->login($usuario, $contrasena);
				if ($localSiest !== null && isset($localSiest['roles'][0]['id']) && $localSiest['roles'][0]['id'] === '41') {
					return $localSiest;
				}
				throw new RuntimeException('Credenciales inválidas para la empresa.');
			}

			return $this->remoteLogin($usuario, $contrasena);
		}

		throw new RuntimeException('SIEST_AUTH_DRIVER inválido. Usa database o remote.');
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
