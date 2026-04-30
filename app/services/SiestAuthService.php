<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class SiestAuthService
{
	public function login(string $usuario, string $contrasena): array
	{
		$driver = strtolower(trim(Env::get('SIEST_AUTH_DRIVER', 'database') ?? 'database'));

		$usuario = trim($usuario);
		if ($driver === 'database') {
			$localSiest = (new LocalSiestService())->login($usuario, $contrasena);
			if ($localSiest !== null) {
				return $localSiest;
			}

			throw new RuntimeException('Usuario no encontrado en SIEst.');
		}

		if ($driver === 'remote') {
			return $this->remoteLogin($usuario, $contrasena);
		}

		throw new RuntimeException('SIEST_AUTH_DRIVER invalido. Usa database o remote.');
	}

	private function remoteLogin(string $usuario, string $contrasena): array
	{
		$url = Env::get('SIEST_LOGIN_URL', 'https://www.utdelacosta.edu.mx/SIEstBackend/api/v1/login');
		$ch = curl_init($url);
		if ($ch === false) {
			throw new RuntimeException('No se pudo iniciar conexion con SIEst.');
		}

		$userField = Env::get('SIEST_LOGIN_USER_FIELD', 'usuario') ?? 'usuario';
		$passwordField = Env::get('SIEST_LOGIN_PASSWORD_FIELD', 'contrasena') ?? 'contrasena';

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => json_encode([
				$userField => $usuario,
				$passwordField => $contrasena,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			CURLOPT_TIMEOUT => (int) (Env::get('SIEST_TIMEOUT', '15') ?? '15'),
		]);

		$response = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($response === false || $status >= 400) {
			$detail = $error !== '' ? ' ' . $error : '';
			throw new RuntimeException('SIEst rechazo la autenticacion.' . $detail);
		}

		$decoded = json_decode((string) $response, true);
		$payload = $this->extractPayload($decoded, trim((string) $response));
		if ($payload === null) {
			throw new RuntimeException('Respuesta invalida del servicio SIEst.');
		}

		$payload['login_identifier'] = $payload['login_identifier'] ?? $usuario;
		$payload['origen'] = $payload['origen'] ?? 'SIEst remote';
		return $payload;
	}

	private function extractPayload(mixed $decoded, string $rawResponse): ?array
	{
		if ($rawResponse !== '' && substr_count($rawResponse, '.') === 2) {
			return $this->decodeJwtPayload($rawResponse);
		}

		if (is_array($decoded) === false) {
			return null;
		}

		foreach ($this->tokenCandidates($decoded) as $token) {
			$payload = $this->decodeJwtPayload($token);
			if ($payload !== null) {
				return $payload;
			}
		}

		if (isset($decoded['data']) && is_array($decoded['data'])) {
			$data = $decoded['data'];
			foreach ($this->tokenCandidates($data) as $token) {
				$payload = $this->decodeJwtPayload($token);
				if ($payload !== null) {
					return $payload;
				}
			}

			if (isset($data['user']) && is_array($data['user'])) {
				return array_merge($data['user'], array_diff_key($data, ['user' => true]));
			}

			if ($this->looksLikePayload($data)) {
				return $data;
			}
		}

		return $this->looksLikePayload($decoded) ? $decoded : null;
	}

	/**
	 * @return list<string>
	 */
	private function tokenCandidates(array $payload): array
	{
		$candidates = [];
		foreach (['token', 'jwt', 'access_token'] as $key) {
			if (isset($payload[$key]) && is_string($payload[$key])) {
				$candidates[] = $payload[$key];
			}
		}

		return $candidates;
	}

	private function looksLikePayload(array $payload): bool
	{
		foreach (['sub', 'usuario', 'cve_persona', 'cve_persona_externa', 'roles', 'rol', 'tipo', 'cve_rol'] as $key) {
			if (array_key_exists($key, $payload)) {
				return true;
			}
		}

		return false;
	}

	private function decodeJwtPayload(string $jwt): ?array
	{
		$parts = explode('.', $jwt);
		if (count($parts) !== 3) {
			return null;
		}

		$payloadSegment = strtr($parts[1], '-_', '+/');
		$payloadSegment .= str_repeat('=', (4 - strlen($payloadSegment) % 4) % 4);
		$json = base64_decode($payloadSegment);
		$payload = json_decode($json ?: '', true);
		return is_array($payload) ? $payload : null;
	}
}
