<?php
declare(strict_types=1);

namespace app\models;

class Empresa extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];

		if (!empty($query['search'])) {
			$where[] = '(razon_social ILIKE :search OR nombre_comercial ILIKE :search OR rfc ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		if (!empty($query['zona'])) {
			$where[] = 'zona = :zona';
			$params['zona'] = $query['zona'];
		}
		if (!empty($query['estado'])) {
			$where[] = 'estado = :estado';
			$params['estado'] = $query['estado'];
		}

		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			'SELECT * FROM empresa WHERE ' . $sqlWhere . ' ORDER BY cve_empresa DESC',
			'SELECT COUNT(*) FROM empresa WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
	}

	public function find(string|int $cveEmpresa): ?array
	{
		return $this->fetchOne('SELECT * FROM empresa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
	}

	public function findByPersonaExterna(string|int $cvePersona): ?array
	{
		$columns = $this->tableColumns('empresa');
		foreach (['cve_persona', 'cve_persona_externa'] as $column) {
			if (in_array($column, $columns, true)) {
				return $this->fetchOne(
					'SELECT * FROM empresa WHERE ' . $this->identifier($column) . ' = :cve_persona',
					['cve_persona' => (string) $cvePersona]
				);
			}
		}

		return null;
	}

	public function create(array $data): array
	{
		return $this->insert('empresa', $this->filterTableData('empresa', $data, ['cve_empresa']), 'cve_empresa');
	}

	public function update(string|int $cveEmpresa, array $data): ?array
	{
		return $this->updateById('empresa', 'cve_empresa', $cveEmpresa, $this->filterTableData('empresa', $data, ['cve_empresa']));
	}

	public function vacantes(string|int $cveEmpresa): array
	{
		return $this->fetchAll('SELECT * FROM vw_vacante_completa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
	}

	public function candidatos(string|int $cveEmpresa): array
	{
		return $this->fetchAll(
			'SELECT
				p.cve_postulacion,
				e.cve_egresado,
				e.nombre,
				e.primer_apellido,
				e.segundo_apellido,
				v.cve_vacante,
				v.titulo AS vacante,
				emp.razon_social AS empresa,
				p.porcentaje_coincidencia,
				p.estado,
				p.fecha_postulacion
			FROM postulacion p
			JOIN egresado e ON e.cve_egresado = p.cve_egresado
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			JOIN empresa emp ON emp.cve_empresa = v.cve_empresa
			WHERE v.cve_empresa = :cve_empresa
			  AND p.porcentaje_coincidencia >= 80
			ORDER BY p.porcentaje_coincidencia DESC, p.fecha_postulacion DESC',
			['cve_empresa' => $cveEmpresa]
		);
	}
}
