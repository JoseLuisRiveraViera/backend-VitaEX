<?php
declare(strict_types=1);

namespace app\models;

class Postulacion extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];
		if (!empty($query['estatus'])) {
			$where[] = 'estado = :estado';
			$params['estado'] = $query['estatus'];
		}
		if (!empty($query['cve_vacante'])) {
			$where[] = 'cve_vacante = :cve_vacante';
			$params['cve_vacante'] = $query['cve_vacante'];
		}
		if (!empty($query['cve_egresado'])) {
			$where[] = 'cve_egresado = :cve_egresado';
			$params['cve_egresado'] = $query['cve_egresado'];
		}
		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			'SELECT * FROM postulacion WHERE ' . $sqlWhere . ' ORDER BY cve_postulacion DESC',
			'SELECT COUNT(*) FROM postulacion WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
	}

	public function find(string|int $id): ?array
	{
		return $this->fetchOne('SELECT * FROM postulacion WHERE cve_postulacion = :id', ['id' => $id]);
	}

	public function create(string|int $cveEgresado, string|int $cveVacante): array
	{
		return $this->insert('postulacion', [
			'cve_egresado' => $cveEgresado,
			'cve_vacante' => $cveVacante,
		], 'cve_postulacion');
	}

	public function actualizarEstatus(string|int $cvePostulacion, array $data): ?array
	{
		$payload = $this->filterTableData('postulacion', $data, ['cve_postulacion', 'cve_egresado', 'cve_vacante', 'porcentaje_coincidencia']);
		if (($payload['estado'] ?? null) !== 'contratado') {
			return $this->updateById('postulacion', 'cve_postulacion', $cvePostulacion, $payload);
		}

		$this->db->beginTransaction();
		try {
			$postulacion = $this->updateById('postulacion', 'cve_postulacion', $cvePostulacion, $payload);
			$exists = $this->fetchOne('SELECT * FROM contratacion WHERE cve_postulacion = :id', ['id' => $cvePostulacion]);
			if ($exists === null) {
				$puesto = $data['puesto'] ?? $this->scalar(
					'SELECT v.titulo
					FROM postulacion p
					JOIN vacante v ON v.cve_vacante = p.cve_vacante
					WHERE p.cve_postulacion = :id',
					['id' => $cvePostulacion]
				);
				$this->insert('contratacion', [
					'cve_postulacion' => $cvePostulacion,
					'puesto' => $puesto ?: 'Puesto contratado',
				], 'cve_contratacion');
			}
			$this->db->commit();
			return $postulacion;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function porVacante(string|int $cveVacante): array
	{
		return $this->fetchAll('SELECT * FROM postulacion WHERE cve_vacante = :cve_vacante ORDER BY cve_postulacion DESC', ['cve_vacante' => $cveVacante]);
	}
}
