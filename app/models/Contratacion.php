<?php
declare(strict_types=1);

namespace app\models;

class Contratacion extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll('SELECT * FROM contratacion ORDER BY cve_contratacion DESC');
	}

	public function find(string|int $id): ?array
	{
		return $this->fetchOne('SELECT * FROM contratacion WHERE cve_contratacion = :id', ['id' => $id]);
	}

	public function porEmpresa(string|int $cveEmpresa): array
	{
		return $this->fetchAll(
			'SELECT c.*
			FROM contratacion c
			JOIN postulacion p ON p.cve_postulacion = c.cve_postulacion
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			WHERE v.cve_empresa = :cve_empresa
			ORDER BY c.cve_contratacion DESC',
			['cve_empresa' => $cveEmpresa]
		);
	}

	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll(
			'SELECT c.*
			FROM contratacion c
			JOIN postulacion p ON p.cve_postulacion = c.cve_postulacion
			WHERE p.cve_egresado = :cve_egresado
			ORDER BY c.cve_contratacion DESC',
			['cve_egresado' => $cveEgresado]
		);
	}

	public function create(array $data): array
	{
		return $this->insert('contratacion', $this->filterTableData('contratacion', $data, ['cve_contratacion']), 'cve_contratacion');
	}

	public function confirmarEgresado(string|int $id): ?array
	{
		return $this->fetchOne(
			'UPDATE contratacion
			SET confirmada_egresado = true, fecha_confirmacion_egresado = now()
			WHERE cve_contratacion = :id
			RETURNING *',
			['id' => $id]
		);
	}
}
