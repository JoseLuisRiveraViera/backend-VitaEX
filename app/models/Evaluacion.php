<?php
declare(strict_types=1);

namespace app\models;

class Evaluacion extends BaseModel
{
	public function tiposPrueba(): array
	{
		return $this->fetchAll('SELECT * FROM tipo_prueba ORDER BY cve_tipo_prueba');
	}

	public function preguntas(string|int $cveTipoPrueba, ?int $cveCarrera = null): array
	{
		$params = [
			'cve_tipo_prueba' => $cveTipoPrueba,
			'estado_pregunta' => 'activo',
			'estado_prueba' => 'activo',
		];

		$whereCarrera = '';
		if ($cveCarrera !== null) {
			$whereCarrera = ' AND (pr.cve_carrera = :cve_carrera OR pr.cve_carrera IS NULL)';
			$params['cve_carrera'] = $cveCarrera;
		}

		return $this->fetchAll(
			'SELECT
				p.cve_pregunta,
				p.cve_prueba,
				p.texto,
				p.tipo_pregunta,
				p.orden,
				p.ponderacion,
				COALESCE(
					json_agg(
						json_build_object(
							\'cve_opcion_respuesta\', o.cve_opcion_respuesta,
							\'cve_pregunta\', o.cve_pregunta,
							\'texto\', o.texto,
							\'valor\', o.valor,
							\'es_correcta\', o.es_correcta,
							\'orden\', o.orden
						)
						ORDER BY o.orden
					) FILTER (WHERE o.cve_opcion_respuesta IS NOT NULL),
					\'[]\'
				) AS opciones
			FROM pregunta p
			JOIN prueba pr ON pr.cve_prueba = p.cve_prueba
			LEFT JOIN opcion_respuesta o ON o.cve_pregunta = p.cve_pregunta
			WHERE pr.cve_tipo_prueba = :cve_tipo_prueba
			  ' . $whereCarrera . '
			  AND p.estado = :estado_pregunta
			  AND pr.estado = :estado_prueba
			GROUP BY p.cve_pregunta
			ORDER BY pr.cve_carrera DESC NULLS LAST, p.orden, p.cve_pregunta',
			$params
		);
	}

	public function iniciar(array $data): array
	{
		$data['estado'] = $data['estado'] ?? 'iniciada';
		$data['fecha_inicio'] = $data['fecha_inicio'] ?? date('c');
		return $this->insert('evaluacion', $this->filterTableData('evaluacion', $data, ['cve_evaluacion']), 'cve_evaluacion');
	}

	public function responder(string|int $cveEvaluacion, array $data): array
	{
		$data['cve_evaluacion'] = $cveEvaluacion;
		$this->db->beginTransaction();
		try {
			$this->execute(
				'DELETE FROM respuesta_evaluacion WHERE cve_evaluacion = :cve_evaluacion AND cve_pregunta = :cve_pregunta',
				[
					'cve_evaluacion' => $cveEvaluacion,
					'cve_pregunta' => $data['cve_pregunta'] ?? 0,
				]
			);
			$row = $this->insert('respuesta_evaluacion', $this->filterTableData('respuesta_evaluacion', $data, ['cve_respuesta_evaluacion']), 'cve_respuesta_evaluacion');
			$this->db->commit();
			return $row;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function finalizar(string|int $cveEvaluacion, array $data): ?array
	{
		$data['estado'] = $data['estado'] ?? 'finalizada';
		$data['fecha_finalizacion'] = $data['fecha_finalizacion'] ?? date('c');

		// 1. Calcular puntaje de esta evaluación (0-100)
		// Puntaje_Cat = (Puntos_Obtenidos / 50) * 100
		$puntosObtenidos = $this->scalar(
			'SELECT SUM(o.valor)
			 FROM respuesta_evaluacion re
			 JOIN opcion_respuesta o ON o.cve_opcion_respuesta = re.cve_opcion_respuesta
			 WHERE re.cve_evaluacion = :cve_evaluacion',
			['cve_evaluacion' => $cveEvaluacion]
		);
		
		$puntajeCalculado = ($puntosObtenidos / 50.0) * 100.0;
		$data['puntaje_obtenido'] = $puntajeCalculado;

		$this->db->beginTransaction();
		try {
			$evaluacion = $this->updateById('evaluacion', 'cve_evaluacion', $cveEvaluacion, $this->filterTableData('evaluacion', $data, ['cve_evaluacion', 'cve_egresado', 'cve_prueba']));
			
			if ($evaluacion) {
				$this->actualizarPuntajeGlobal((int) $evaluacion['cve_egresado']);
			}
			
			$this->db->commit();
			return $evaluacion;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function actualizarPuntajeGlobal(int $cveEgresado): void
	{
		// Obtener puntajes de las 4 dimensiones
		$puntajes = $this->fetchAll(
			'SELECT tp.categoria, e.puntaje_obtenido
			 FROM evaluacion e
			 JOIN prueba pr ON pr.cve_prueba = e.cve_prueba
			 JOIN tipo_prueba tp ON tp.cve_tipo_prueba = pr.cve_tipo_prueba
			 WHERE e.cve_egresado = :cve_egresado AND e.estado = \'finalizada\'',
			['cve_egresado' => $cveEgresado]
		);

		$scores = [
			'psicometrica' => 0,
			'cognitiva' => 0,
			'tecnica' => 0,
			'proyectiva' => 0
		];

		foreach ($puntajes as $p) {
			$scores[$p['categoria']] = (float) $p['puntaje_obtenido'];
		}

		// Global = Psicometrica(0.25) + Cognitiva(0.25) + Técnica(0.30) + Proyectiva(0.20)
		$global = ($scores['psicometrica'] * 0.25) + 
				  ($scores['cognitiva'] * 0.25) + 
				  ($scores['tecnica'] * 0.30) + 
				  ($scores['proyectiva'] * 0.20);

		// Determinar nivel
		$nivel = 'Muy bajo';
		if ($global >= 90) $nivel = 'Muy superior';
		elseif ($global >= 75) $nivel = 'Superior';
		elseif ($global >= 50) $nivel = 'Promedio';
		elseif ($global >= 25) $nivel = 'Bajo';

		// Guardar en resultado_global_egresado
		$exists = $this->scalar('SELECT 1 FROM resultado_global_egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
		
		$dataGlobal = [
			'cve_egresado' => $cveEgresado,
			'puntaje_psicometrica' => $scores['psicometrica'],
			'puntaje_cognitiva' => $scores['cognitiva'],
			'puntaje_tecnica' => $scores['tecnica'],
			'puntaje_proyectiva' => $scores['proyectiva'],
			'puntaje_global' => $global,
			'nivel_interpretacion' => $nivel,
			'fecha_actualizacion' => date('c')
		];

		if ($exists) {
			$this->updateById('resultado_global_egresado', 'cve_egresado', $cveEgresado, $dataGlobal);
		} else {
			$this->insert('resultado_global_egresado', $dataGlobal, 'cve_resultado_global');
		}
	}
}
