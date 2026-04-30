<?php
declare(strict_types=1);

namespace app\models;

class TrayectoriaAcademica extends BaseModel
{
	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll(
			'SELECT * FROM trayectoria_academica WHERE cve_egresado = :cve_egresado ORDER BY fecha_inicio DESC NULLS LAST',
			['cve_egresado' => $cveEgresado]
		);
	}

	public function create(string|int $cveEgresado, array $data): array
	{
		$data['cve_egresado'] = $cveEgresado;
		return $this->insert(
			'trayectoria_academica',
			$this->filterTableData('trayectoria_academica', $data, ['cve_trayectoria_academica']),
			'cve_trayectoria_academica'
		);
	}

	public function update(string|int $id, array $data): ?array
	{
		return $this->updateById(
			'trayectoria_academica',
			'cve_trayectoria_academica',
			$id,
			$this->filterTableData('trayectoria_academica', $data, ['cve_trayectoria_academica', 'cve_egresado'])
		);
	}

	public function delete(string|int $id): int
	{
		return $this->execute(
			'DELETE FROM trayectoria_academica WHERE cve_trayectoria_academica = :id',
			['id' => $id]
		);
	}
}
