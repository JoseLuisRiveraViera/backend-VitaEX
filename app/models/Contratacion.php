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

	public function evaluarDesempeno(string|int $cveEmpresa, array $data): ?array
	{
		$this->ensureDesempenoColumns();

		$postulacion = $this->fetchOne(
			'SELECT p.cve_postulacion, p.cve_egresado, p.estado, v.cve_empresa, v.titulo
			FROM postulacion p
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			WHERE p.cve_postulacion = :cve_postulacion
			  AND v.cve_empresa = :cve_empresa',
			[
				'cve_postulacion' => $data['cve_postulacion'],
				'cve_empresa' => $cveEmpresa,
			]
		);
		if ($postulacion === null) {
			return null;
		}

		$contratacion = $this->fetchOne(
			'SELECT *
			FROM contratacion
			WHERE cve_postulacion = :cve_postulacion
			ORDER BY cve_contratacion DESC
			LIMIT 1',
			['cve_postulacion' => $data['cve_postulacion']]
		);

		if ($contratacion === null) {
			$contratacion = $this->create([
				'cve_postulacion' => $data['cve_postulacion'],
				'puesto' => $postulacion['titulo'] ?? null,
				'observacion' => 'Registro creado desde evaluación de desempeño empresarial.',
			]);
		}

		return $this->fetchOne(
			'UPDATE contratacion
			SET calificacion_desempeno = :calificacion,
				comentario_desempeno = :comentario,
				fecha_evaluacion_desempeno = now()
			WHERE cve_contratacion = :cve_contratacion
			RETURNING *',
			[
				'calificacion' => (int) $data['calificacion'],
				'comentario' => trim((string) $data['comentario']),
				'cve_contratacion' => $contratacion['cve_contratacion'],
			]
		);
	}

	private function ensureDesempenoColumns(): void
	{
		$this->execute('ALTER TABLE contratacion ADD COLUMN IF NOT EXISTS calificacion_desempeno integer');
		$this->execute('ALTER TABLE contratacion ADD COLUMN IF NOT EXISTS comentario_desempeno text');
		$this->execute('ALTER TABLE contratacion ADD COLUMN IF NOT EXISTS fecha_evaluacion_desempeno timestamp with time zone');
	}
}
