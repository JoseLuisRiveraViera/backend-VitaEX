<?php
declare(strict_types=1);

namespace app\models;

class Vacante extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];

		if (!empty($query['search'])) {
			$where[] = '(titulo ILIKE :search OR descripcion ILIKE :search OR razon_social ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		foreach (['area', 'municipio', 'estado'] as $field) {
			if (!empty($query[$field])) {
				$where[] = $this->identifier($field) . ' = :' . $field;
				$params[$field] = $query[$field];
			}
		}
		if (!empty($query['zona'])) {
			$where[] = 'zona = :zona';
			$params['zona'] = $query['zona'];
		}

		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			'SELECT * FROM vw_vacante_completa WHERE ' . $sqlWhere . ' ORDER BY fecha_publicacion DESC, cve_vacante DESC',
			'SELECT COUNT(*) FROM vw_vacante_completa WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
	}

	public function find(string|int $cveVacante): ?array
	{
		return $this->fetchOne('SELECT * FROM vw_vacante_completa WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
	}

	public function create(array $data): array
	{
		$perfil = $data['perfil_idoneo'] ?? [];
		unset($data['perfil_idoneo']);

		$this->db->beginTransaction();
		try {
			$vacante = $this->insert('vacante', $this->filterTableData('vacante', $data, ['cve_vacante']), 'cve_vacante');

			if (is_array($perfil) && $perfil !== []) {
				$perfil['cve_vacante'] = $vacante['cve_vacante'];
				$this->insert('perfil_idoneo', $this->filterTableData('perfil_idoneo', $perfil, ['cve_perfil_idoneo']), 'cve_perfil_idoneo');
			}

			$this->db->commit();
			return $vacante;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function update(string|int $cveVacante, array $data): ?array
	{
		unset($data['perfil_idoneo']);
		return $this->updateById('vacante', 'cve_vacante', $cveVacante, $this->filterTableData('vacante', $data, ['cve_vacante']));
	}

	public function softDelete(string|int $cveVacante): ?array
	{
		return $this->fetchOne('UPDATE vacante SET estado = :estado WHERE cve_vacante = :cve_vacante RETURNING *', [
			'estado' => 'cancelada',
			'cve_vacante' => $cveVacante,
		]);
	}

	public function candidatos(string|int $cveVacante): array
	{
		return $this->fetchAll('SELECT * FROM vw_dashboard_candidato_idoneo WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
	}

	public function crearPerfilIdoneo(string|int $cveVacante, array $data): array
	{
		$data['cve_vacante'] = $cveVacante;
		return $this->insert('perfil_idoneo', $this->filterTableData('perfil_idoneo', $data, ['cve_perfil_idoneo']), 'cve_perfil_idoneo');
	}

	public function actualizarPerfilIdoneo(string|int $cveVacante, array $data): ?array
	{
		$current = $this->fetchOne('SELECT * FROM perfil_idoneo WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
		if ($current === null) {
			return $this->crearPerfilIdoneo($cveVacante, $data);
		}

		return $this->updateById('perfil_idoneo', 'cve_perfil_idoneo', $current['cve_perfil_idoneo'], $this->filterTableData('perfil_idoneo', $data, ['cve_perfil_idoneo', 'cve_vacante']));
	}
}
