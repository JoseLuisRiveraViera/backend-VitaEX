<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;

class JwtService
{
	public function create(array $payload, int $ttlSeconds = 3600): string
	{
		$now = time();
		$ttlSeconds = (int) (Env::get('JWT_TTL', (string) $ttlSeconds) ?? $ttlSeconds);
		$payload['iat'] = $payload['iat'] ?? $now;
		$payload['exp'] = $payload['exp'] ?? ($now + $ttlSeconds);

		$header = ['typ' => 'JWT', 'alg' => 'HS256'];
		$segments = [
			$this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'),
			$this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'),
		];

		$signature = hash_hmac('sha256', implode('.', $segments), $this->secret(), true);
		$segments[] = $this->base64UrlEncode($signature);

		return implode('.', $segments);
	}

	public function verify(string $token): ?array
	{
		$parts = explode('.', $token);
		if (count($parts) !== 3) {
			return null;
		}

		[$header, $payload, $signature] = $parts;
		$expected = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $this->secret(), true));

		if (hash_equals($expected, $signature) === false) {
			return null;
		}

		$decoded = json_decode($this->base64UrlDecode($payload), true);
		if (is_array($decoded) === false) {
			return null;
		}

		if (isset($decoded['exp']) && (int) $decoded['exp'] < time()) {
			return null;
		}

		return $decoded;
	}

	private function secret(): string
	{
		return Env::get('JWT_SECRET', 'cambia-este-secreto-en-produccion') ?? 'cambia-este-secreto-en-produccion';
	}

	private function base64UrlEncode(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	private function base64UrlDecode(string $value): string
	{
		return base64_decode(strtr($value, '-_', '+/')) ?: '';
	}
}
