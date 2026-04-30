<?php
declare(strict_types=1);

namespace app\models;

class Egresado extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];

		if (!empty($query['search'])) {
			$where[] = '(nombre ILIKE :search OR primer_apellido ILIKE :search OR matricula ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		if (!empty($query['carrera'])) {
			$where[] = 'cve_carrera = :carrera';
			$params['carrera'] = $query['carrera'];
		}
		if (!empty($query['estado'])) {
			$where[] = 'estado = :estado';
			$params['estado'] = $query['estado'];
		}

		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			'SELECT * FROM egresado WHERE ' . $sqlWhere . ' ORDER BY cve_egresado DESC',
			'SELECT COUNT(*) FROM egresado WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
	}

	public function find(string|int $cveEgresado): ?array
	{
		return $this->fetchOne('SELECT * FROM egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
	}

	public function findByPersonaExterna(string|int $cvePersona): ?array
	{
		return $this->fetchOne('SELECT * FROM egresado WHERE cve_persona_externa = :cve_persona', ['cve_persona' => (string) $cvePersona]);
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

	public function certificados(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM documento_egresado WHERE cve_egresado = :cve_egresado ORDER BY cve_documento_egresado DESC', ['cve_egresado' => $cveEgresado]);
	}
}
