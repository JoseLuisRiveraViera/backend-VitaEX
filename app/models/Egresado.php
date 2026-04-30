<?php
declare(strict_types=1);

namespace app\models;

class Egresado extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll('SELECT * FROM egresado ORDER BY cve_egresado DESC');
	}

	public function find(string|int $cveEgresado): ?array
	{
		return $this->fetchOne('SELECT * FROM egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
	}

	public function perfil(string|int $cveEgresado): ?array
	{
		return $this->fetchOne('SELECT * FROM vw_perfil_completo_egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
	}

	public function actualizarPerfil(string|int $cveEgresado, array $data): ?array
	{
		$payload = $this->filterTableData('egresado', $data, ['cve_egresado', 'cve_persona_externa', 'matricula', 'cve_carrera']);
		return $this->updateById('egresado', 'cve_egresado', $cveEgresado, $payload);
	}

	public function postulaciones(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM postulacion WHERE cve_egresado = :cve_egresado ORDER BY cve_postulacion DESC', ['cve_egresado' => $cveEgresado]);
	}

	public function evaluaciones(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM evaluacion WHERE cve_egresado = :cve_egresado ORDER BY cve_evaluacion DESC', ['cve_egresado' => $cveEgresado]);
	}
}
