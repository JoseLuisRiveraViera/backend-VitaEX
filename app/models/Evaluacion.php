<?php
declare(strict_types=1);

namespace app\models;

class Evaluacion extends BaseModel
{
	public function tiposPrueba(): array
	{
		return $this->fetchAll('SELECT * FROM tipo_prueba ORDER BY cve_tipo_prueba');
	}

	public function preguntas(string|int $cveTipoPrueba): array
	{
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
			  AND p.estado = :estado_pregunta
			  AND pr.estado = :estado_prueba
			GROUP BY p.cve_pregunta
			ORDER BY p.orden, p.cve_pregunta',
			[
				'cve_tipo_prueba' => $cveTipoPrueba,
				'estado_pregunta' => 'activo',
				'estado_prueba' => 'activo',
			]
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

		return $this->updateById('evaluacion', 'cve_evaluacion', $cveEvaluacion, $this->filterTableData('evaluacion', $data, ['cve_evaluacion', 'cve_egresado', 'cve_prueba']));
	}
}
