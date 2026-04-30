<?php
declare(strict_types=1);

namespace app\models;

class AdministradorUt extends BaseModel
{
	public function findByPersonaExterna(string|int $cvePersona): ?array
	{
		if ($this->hasTable('administrador_ut') === false) {
			return null;
		}

		return $this->fetchOne(
			'SELECT *
			FROM administrador_ut
			WHERE cve_persona_externa = :cve_persona
			  AND estado = \'activo\'
			LIMIT 1',
			['cve_persona' => (string) $cvePersona]
		);
	}

	public function findByLoginIdentifier(string $identifier): ?array
	{
		if ($this->hasTable('administrador_ut') === false) {
			return null;
		}

		$identifier = strtolower(trim($identifier));
		if ($identifier === '') {
			return null;
		}

		return $this->fetchOne(
			'SELECT *
			FROM administrador_ut
			WHERE estado = \'activo\'
			  AND (
				lower(trim(correo_institucional)) = :identifier
				OR lower(trim(cve_persona_externa)) = :identifier
			  )
			ORDER BY
				CASE
					WHEN lower(trim(correo_institucional)) = :identifier THEN 0
					ELSE 1
				END,
				cve_administrador_ut
			LIMIT 1',
			['identifier' => $identifier]
		);
	}
}
