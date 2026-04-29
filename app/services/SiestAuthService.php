<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class SiestAuthService
{
	public function login(string $usuario, string $contrasena): array
	{
		$useMock = filter_var(Env::get('SIEST_AUTH_MOCK', 'true'), FILTER_VALIDATE_BOOLEAN);
		if ($useMock === true) {
			return $this->mockLogin($usuario);
		}

		return $this->remoteLogin($usuario, $contrasena);
	}

	private function mockLogin(string $usuario): array
	{
		$roleId = Env::get('SIEST_MOCK_ROLE_ID', '40') ?? '40';

		return [
			'sub' => Env::get('SIEST_MOCK_SUB', '6628'),
			'usuario' => $usuario,
			'perfil_id' => '1',
			'cve_persona' => Env::get('SIEST_MOCK_CVE_PERSONA', '12668'),
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
		curl_close($ch);

		if ($response === false || $status >= 400) {
			throw new RuntimeException('SIEst rechazó la autenticación. ' . $error);
		}

		$decoded = json_decode((string) $response, true);
		if (is_array($decoded) === false) {
			throw new RuntimeException('Respuesta inválida del servicio SIEst.');
		}

		return $decoded;
	}

	private function roleName(string $roleId): string
	{
		return match ($roleId) {
			'22' => 'Jefe vinculación / Admin UT',
			'41' => 'Empresa',
			default => 'Egresado',
		};
	}
}
