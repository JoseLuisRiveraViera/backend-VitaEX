<?php
declare(strict_types=1);

namespace app\models;

class UsuarioSiest extends BaseModel
{
	public function findLogin(string $usuario): ?array
	{
		if ($this->hasTable('usuario_siest') === false) {
			return null;
		}

		$identifier = $this->normalizeIdentifier($usuario);
		if ($identifier === '') {
			return null;
		}

		$row = $this->fetchOne(
			'SELECT *, \'usuario\' AS login_resuelto_por
			FROM vw_usuario_siest_login us
			WHERE lower(trim(nombre_usuario)) = :usuario AND activo = true
			ORDER BY cve_usuario_siest
			LIMIT 1',
			['usuario' => $identifier]
		);
		if ($row !== null) {
			return $row;
		}

		$row = $this->findByEgresadoIdentifier($identifier);
		if ($row !== null) {
			return $row;
		}

		$row = $this->findByAdministradorIdentifier($identifier);
		if ($row !== null) {
			return $row;
		}

		$row = $this->findByEmpresaIdentifier($identifier);
		if ($row !== null) {
			return $row;
		}

		return null;
	}

	private function findByEgresadoIdentifier(string $identifier): ?array
	{
		if ($this->hasTable('egresado') === false) {
			return null;
		}

		return $this->fetchOne(
			'SELECT us.*, \'egresado\' AS login_resuelto_por
			FROM vw_usuario_siest_login us
			JOIN egresado e ON e.cve_persona_externa = us.cve_persona_externa
			WHERE us.activo = true
			  AND (
				lower(trim(e.matricula)) = :usuario
				OR lower(trim(coalesce(e.correo_institucional, \'\'))) = :usuario
				OR lower(trim(coalesce(e.correo_personal, \'\'))) = :usuario
				OR lower(trim(e.cve_persona_externa)) = :usuario
			  )
			ORDER BY
				CASE
					WHEN lower(trim(e.matricula)) = :usuario THEN 0
					WHEN lower(trim(coalesce(e.correo_institucional, \'\'))) = :usuario THEN 1
					WHEN lower(trim(coalesce(e.correo_personal, \'\'))) = :usuario THEN 2
					ELSE 3
				END,
				us.cve_usuario_siest
			LIMIT 1',
			['usuario' => $identifier]
		);
	}

	private function findByAdministradorIdentifier(string $identifier): ?array
	{
		if ($this->hasTable('administrador_ut') === false) {
			return null;
		}

		return $this->fetchOne(
			'SELECT us.*, \'administrador_ut\' AS login_resuelto_por
			FROM vw_usuario_siest_login us
			JOIN administrador_ut a ON a.cve_persona_externa = us.cve_persona_externa
			WHERE us.activo = true
			  AND (
				lower(trim(a.correo_institucional)) = :usuario
				OR lower(trim(a.cve_persona_externa)) = :usuario
			  )
			ORDER BY
				CASE
					WHEN lower(trim(a.correo_institucional)) = :usuario THEN 0
					ELSE 1
				END,
				us.cve_usuario_siest
			LIMIT 1',
			['usuario' => $identifier]
		);
	}

	private function findByEmpresaIdentifier(string $identifier): ?array
	{
		if ($this->hasTable('empresa') === false) {
			return null;
		}

		if (in_array('cve_persona_externa', $this->tableColumns('empresa'), true)) {
			$row = $this->fetchOne(
				'SELECT us.*, \'empresa\' AS login_resuelto_por
				FROM vw_usuario_siest_login us
				JOIN empresa e ON e.cve_persona_externa = us.cve_persona_externa
				WHERE us.activo = true
				  AND (
					lower(trim(coalesce(e.correo_general, \'\'))) = :usuario
					OR lower(trim(e.cve_persona_externa)) = :usuario
				  )
				ORDER BY us.cve_usuario_siest
				LIMIT 1',
				['usuario' => $identifier]
			);
			if ($row !== null) {
				return $row;
			}
		}

		return $this->fetchOne(
			'WITH empresa_match AS (
				SELECT e.cve_empresa
				FROM empresa e
				WHERE e.estado = \'activo\'
				  AND (
					lower(trim(coalesce(e.correo_general, \'\'))) = :usuario
					OR EXISTS (
						SELECT 1
						FROM contacto_empresa ce
						WHERE ce.cve_empresa = e.cve_empresa
						  AND ce.estado = \'activo\'
						  AND lower(trim(ce.correo)) = :usuario
					)
				  )
				LIMIT 1
			),
			empresa_users AS (
				SELECT us.*
				FROM vw_usuario_siest_login us
				WHERE us.activo = true
				  AND us.clave_rol = \'EMPRESA\'
			)
			SELECT eu.*, \'empresa_correo\' AS login_resuelto_por
			FROM empresa_users eu
			WHERE EXISTS (SELECT 1 FROM empresa_match)
			  AND (SELECT count(*) FROM empresa_users) = 1
			LIMIT 1',
			['usuario' => $identifier]
		);
	}

	private function normalizeIdentifier(string $value): string
	{
		return strtolower(trim($value));
	}
}
