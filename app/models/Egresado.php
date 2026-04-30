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
			$where[] = '(e.nombre ILIKE :search OR e.primer_apellido ILIKE :search OR e.matricula ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		if (!empty($query['carrera'])) {
			$where[] = 'e.cve_carrera = :carrera';
			$params['carrera'] = $query['carrera'];
		}
		if (!empty($query['estado'])) {
			$where[] = 'e.estado = :estado';
			$params['estado'] = $query['estado'];
		}

		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			$this->selectSql() . ' WHERE ' . $sqlWhere . ' ORDER BY e.cve_egresado DESC',
			'SELECT COUNT(*) FROM egresado e WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 100)
		);
	}

	public function find(string|int $cveEgresado): ?array
	{
		return $this->fetchOne($this->selectSql() . ' WHERE e.cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
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
		return $this->fetchAll(
			'SELECT
				p.*,
				v.titulo AS vacante,
				v.razon_social AS empresa,
				v.nombre_comercial,
				v.area
			FROM postulacion p
			JOIN vw_vacante_completa v ON v.cve_vacante = p.cve_vacante
			WHERE p.cve_egresado = :cve_egresado
			ORDER BY p.cve_postulacion DESC',
			['cve_egresado' => $cveEgresado]
		);
	}

	public function evaluaciones(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM evaluacion WHERE cve_egresado = :cve_egresado ORDER BY cve_evaluacion DESC', ['cve_egresado' => $cveEgresado]);
	}

	public function certificados(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM documento_egresado WHERE cve_egresado = :cve_egresado ORDER BY cve_documento_egresado DESC', ['cve_egresado' => $cveEgresado]);
	}

	private function selectSql(): string
	{
		return 'SELECT
				e.*,
				c.nombre AS carrera,
				c.clave_oficial AS abreviatura_carrera,
				u.estado AS estado_ubicacion,
				u.municipio,
				u.localidad,
				p.puntaje_psicometrica,
				p.puntaje_cognitiva,
				p.puntaje_tecnica,
				p.puntaje_proyectiva
			FROM egresado e
			JOIN carrera c ON c.cve_carrera = e.cve_carrera
			LEFT JOIN ubicacion u ON u.cve_ubicacion = e.cve_ubicacion
			LEFT JOIN vw_puntaje_egresado p ON p.cve_egresado = e.cve_egresado';
	}
}
