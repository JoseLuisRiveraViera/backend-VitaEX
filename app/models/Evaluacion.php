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
		$orderCarrera = '';
		$groupCarrera = '';
		if ($cveCarrera !== null && $this->hasColumn('prueba', 'cve_carrera')) {
			$hasSpecificBank = (bool) $this->scalar(
				'SELECT 1
				FROM prueba
				WHERE cve_tipo_prueba = :cve_tipo_prueba
				  AND cve_carrera = :cve_carrera
				  AND estado = :estado_prueba
				LIMIT 1',
				[
					'cve_tipo_prueba' => $cveTipoPrueba,
					'cve_carrera' => $cveCarrera,
					'estado_prueba' => 'activo',
				]
			);

			$whereCarrera = $hasSpecificBank
				? ' AND pr.cve_carrera = :cve_carrera'
				: ' AND pr.cve_carrera IS NULL';
			$orderCarrera = 'pr.cve_carrera DESC NULLS LAST, ';
			$groupCarrera = ', pr.cve_carrera';
			if ($hasSpecificBank) {
				$params['cve_carrera'] = $cveCarrera;
			}
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
			GROUP BY p.cve_pregunta' . $groupCarrera . '
			ORDER BY ' . $orderCarrera . 'p.orden, p.cve_pregunta',
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
		$this->db->beginTransaction();
		try {
			$row = $this->guardarRespuesta($cveEvaluacion, $data);
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
		$respuestas = is_array($data['respuestas'] ?? null) ? $data['respuestas'] : [];
		unset($data['respuestas']);

		$this->db->beginTransaction();
		try {
			foreach ($respuestas as $respuesta) {
				if (!is_array($respuesta)) {
					continue;
				}

				$this->guardarRespuesta($cveEvaluacion, [
					'cve_pregunta' => $respuesta['cve_pregunta'] ?? null,
					'cve_opcion_respuesta' => $respuesta['cve_opcion_respuesta'] ?? null,
				]);
			}

			$puntos = $this->fetchOne(
				'SELECT
					COALESCE(SUM(o.valor), 0) AS obtenidos,
					COALESCE(SUM(maximos.valor_maximo), 0) AS maximo
				 FROM respuesta_evaluacion re
				 JOIN opcion_respuesta o ON o.cve_opcion_respuesta = re.cve_opcion_respuesta
				 JOIN (
					SELECT cve_pregunta, MAX(valor) AS valor_maximo
					FROM opcion_respuesta
					GROUP BY cve_pregunta
				 ) maximos ON maximos.cve_pregunta = re.cve_pregunta
				 WHERE re.cve_evaluacion = :cve_evaluacion',
				['cve_evaluacion' => $cveEvaluacion]
			);

			$puntosObtenidos = (float) ($puntos['obtenidos'] ?? 0);
			$puntosMaximos = (float) ($puntos['maximo'] ?? 0);
			$data['puntaje_obtenido'] = $puntosMaximos > 0 ? ($puntosObtenidos / $puntosMaximos) * 100.0 : 0;

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
			'proyectiva' => 0,
		];

		foreach ($puntajes as $p) {
			$scores[$p['categoria']] = (float) $p['puntaje_obtenido'];
		}

		$global = ($scores['psicometrica'] * 0.25) +
			($scores['cognitiva'] * 0.25) +
			($scores['tecnica'] * 0.30) +
			($scores['proyectiva'] * 0.20);

		$nivel = 'Muy bajo';
		if ($global >= 90) {
			$nivel = 'Muy superior';
		} elseif ($global >= 75) {
			$nivel = 'Superior';
		} elseif ($global >= 50) {
			$nivel = 'Promedio';
		} elseif ($global >= 25) {
			$nivel = 'Bajo';
		}

		if (!$this->hasTable('resultado_global_egresado')) {
			return;
		}

		$exists = $this->scalar('SELECT 1 FROM resultado_global_egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
		$dataGlobal = [
			'cve_egresado' => $cveEgresado,
			'puntaje_psicometrica' => $scores['psicometrica'],
			'puntaje_cognitiva' => $scores['cognitiva'],
			'puntaje_tecnica' => $scores['tecnica'],
			'puntaje_proyectiva' => $scores['proyectiva'],
			'puntaje_global' => $global,
			'nivel_interpretacion' => $nivel,
			'fecha_actualizacion' => date('c'),
		];

		if ($exists) {
			$this->updateById('resultado_global_egresado', 'cve_egresado', $cveEgresado, $dataGlobal);
		} else {
			$this->insert('resultado_global_egresado', $dataGlobal, 'cve_resultado_global');
		}
	}

	private function guardarRespuesta(string|int $cveEvaluacion, array $data): array
	{
		$data['cve_evaluacion'] = $cveEvaluacion;
		$this->execute(
			'DELETE FROM respuesta_evaluacion WHERE cve_evaluacion = :cve_evaluacion AND cve_pregunta = :cve_pregunta',
			[
				'cve_evaluacion' => $cveEvaluacion,
				'cve_pregunta' => $data['cve_pregunta'] ?? 0,
			]
		);

		return $this->insert('respuesta_evaluacion', $this->filterTableData('respuesta_evaluacion', $data, ['cve_respuesta_evaluacion']), 'cve_respuesta_evaluacion');
	}

	private function hasColumn(string $table, string $column): bool
	{
		return (bool) $this->scalar(
			'SELECT EXISTS (
				SELECT 1 FROM information_schema.columns
				WHERE table_schema = :schema AND table_name = :table AND column_name = :column
			)',
			[
				'schema' => $this->schema,
				'table' => $table,
				'column' => $column,
			]
		);
	}
}
