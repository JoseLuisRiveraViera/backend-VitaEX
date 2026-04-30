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
			'SELECT p.*, COALESCE(json_agg(o.*) FILTER (WHERE o.cve_opcion_respuesta IS NOT NULL), \'[]\') AS opciones
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
		return $this->insert('evaluacion', $this->filterTableData('evaluacion', $data, ['cve_evaluacion']), 'cve_evaluacion');
	}

	public function responder(string|int $cveEvaluacion, array $data): array
	{
		$data['cve_evaluacion'] = $cveEvaluacion;
		return $this->insert('respuesta_evaluacion', $this->filterTableData('respuesta_evaluacion', $data, ['cve_respuesta_evaluacion']), 'cve_respuesta_evaluacion');
	}

	public function finalizar(string|int $cveEvaluacion, array $data): ?array
	{
		$data['estado'] = $data['estado'] ?? 'finalizada';
		$data['fecha_finalizacion'] = $data['fecha_finalizacion'] ?? date('c');

		return $this->updateById('evaluacion', 'cve_evaluacion', $cveEvaluacion, $this->filterTableData('evaluacion', $data, ['cve_evaluacion', 'cve_egresado', 'cve_prueba']));
	}
}
