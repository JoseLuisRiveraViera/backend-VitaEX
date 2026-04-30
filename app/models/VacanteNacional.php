<?php
declare(strict_types=1);

namespace app\models;

class VacanteNacional extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];
		if (!empty($query['search'])) {
			$where[] = '(titulo ILIKE :search OR descripcion ILIKE :search OR empresa_externa ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			'SELECT * FROM vacante_api WHERE ' . $sqlWhere . ' ORDER BY fecha_obtencion DESC, cve_vacante_api DESC',
			'SELECT COUNT(*) FROM vacante_api WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
	}

	public function fuente(string $nombre, string $url): array
	{
		return $this->fetchOne(
			'INSERT INTO fuente_api (nombre, url_base)
			VALUES (:nombre, :url_base)
			ON CONFLICT (nombre) DO UPDATE SET url_base = EXCLUDED.url_base
			RETURNING *',
			['nombre' => $nombre, 'url_base' => $url]
		) ?? [];
	}

	public function upsert(array $data): array
	{
		$sql = 'INSERT INTO vacante_api (
				cve_fuente_api, id_externo, titulo, descripcion, empresa_externa,
				ubicacion_texto, modalidad, salario_minimo, salario_maximo, url_original,
				fecha_publicacion, datos_originales
			) VALUES (
				:cve_fuente_api, :id_externo, :titulo, :descripcion, :empresa_externa,
				:ubicacion_texto, :modalidad, :salario_minimo, :salario_maximo, :url_original,
				:fecha_publicacion, :datos_originales
			)
			ON CONFLICT (cve_fuente_api, id_externo) DO UPDATE SET
				titulo = EXCLUDED.titulo,
				descripcion = EXCLUDED.descripcion,
				empresa_externa = EXCLUDED.empresa_externa,
				ubicacion_texto = EXCLUDED.ubicacion_texto,
				modalidad = EXCLUDED.modalidad,
				salario_minimo = EXCLUDED.salario_minimo,
				salario_maximo = EXCLUDED.salario_maximo,
				url_original = EXCLUDED.url_original,
				fecha_publicacion = EXCLUDED.fecha_publicacion,
				datos_originales = EXCLUDED.datos_originales,
				fecha_obtencion = now()
			RETURNING *';

		return $this->fetchOne($sql, $data) ?? [];
	}
}
