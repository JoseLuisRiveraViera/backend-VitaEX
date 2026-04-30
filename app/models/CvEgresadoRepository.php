<?php
declare(strict_types=1);

namespace app\models;

class CvEgresadoRepository extends BaseModel
{
	public function findProfileById(int $id): ?array
	{
		// Usamos la vista existente si es posible, o combinamos con egresado
		$sql = 'SELECT 
					e.*,
					c.nombre AS carrera,
					c.clave_oficial AS abreviatura_carrera,
					u.estado AS estado_ubicacion,
					u.municipio,
					u.localidad
				FROM egresado e
				LEFT JOIN carrera c ON c.cve_carrera = e.cve_carrera
				LEFT JOIN ubicacion u ON u.cve_ubicacion = e.cve_ubicacion
				WHERE e.cve_egresado = :id';
		return $this->fetchOne($sql, ['id' => $id]);
	}

	public function findExperiencesByEgresadoId(int $id): array
	{
		// Verificamos si la tabla existe en la bd (es opcional)
		try {
			return $this->fetchAll('SELECT * FROM experiencia_laboral WHERE cve_egresado = :id ORDER BY fecha_inicio DESC', ['id' => $id]);
		} catch (\Throwable $e) {
			return [];
		}
	}

	public function findSkillsByEgresadoId(int $id): array
	{
		try {
			return $this->fetchAll(
				'SELECT h.nombre, eh.nivel 
				 FROM egresado_habilidad eh
				 JOIN habilidad h ON h.cve_habilidad = eh.cve_habilidad
				 WHERE eh.cve_egresado = :id', 
				['id' => $id]
			);
		} catch (\Throwable $e) {
			return [];
		}
	}

	public function findTestResultsByEgresadoId(int $id): array
	{
		// Según el esquema, usamos evaluacion y prueba / tipo_prueba
		$sql = 'SELECT 
					tp.categoria,
					tp.nombre,
					e.puntaje_obtenido
				FROM evaluacion e
				JOIN prueba p ON p.cve_prueba = e.cve_prueba
				JOIN tipo_prueba tp ON tp.cve_tipo_prueba = p.cve_tipo_prueba
				WHERE e.cve_egresado = :id AND e.estado = \'finalizada\'';
		return $this->fetchAll($sql, ['id' => $id]);
	}

	public function findCertificationsByEgresadoId(int $id): array
	{
		try {
			// Del DDL o de la tabla de documentos
			return $this->fetchAll('SELECT * FROM documento_egresado WHERE cve_egresado = :id ORDER BY fecha_emision DESC', ['id' => $id]);
		} catch (\Throwable $e) {
			return [];
		}
	}
}
