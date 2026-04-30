<?php
declare(strict_types=1);

namespace app\services;

use app\models\UsuarioSiest;
use RuntimeException;

class LocalSiestService
{
	public function login(string $usuario, string $contrasena): ?array
	{
		$loginIdentifier = trim($usuario);
		$row = (new UsuarioSiest())->findLogin($loginIdentifier);
		if ($row === null) {
			return null;
		}

		if (password_verify($contrasena, (string) $row['contrasena_hash']) === false) {
			throw new RuntimeException('Credenciales invalidas para SIEst.');
		}

		$roleId = $this->roleIdFromClave((string) $row['clave_rol']);

		return [
			'sub' => (string) $row['cve_persona_externa'],
			'usuario' => (string) $row['nombre_usuario'],
			'perfil_id' => (string) $row['cve_rol'],
			'cve_persona' => (string) $row['cve_persona_externa'],
			'roles' => [
				[
					'id' => $roleId,
					'clave' => (string) $row['clave_rol'],
					'nombre' => (string) $row['descripcion_rol'],
				],
			],
			'iat' => time(),
			'exp' => time() + 3600,
			'login_identifier' => $loginIdentifier,
			'login_resuelto_por' => $row['login_resuelto_por'] ?? 'usuario',
			'origen' => 'SIEst database',
		];
	}

	private function roleIdFromClave(string $clave): string
	{
		return match ($clave) {
			'EGRESADO' => '40',
			'EMPRESA' => '41',
			'ADMINISTRADOR_UT', 'JEFE_VINCULACION' => '22',
			default => '1',
		};
	}
}
