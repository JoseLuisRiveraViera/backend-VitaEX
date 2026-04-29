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
			LEFT JOIN opcion_respuesta o ON o.cve_pregunta = p.cve_pregunta
			WHERE p.cve_tipo_prueba = :cve_tipo_prueba
			GROUP BY p.cve_pregunta
			ORDER BY p.cve_pregunta',
			['cve_tipo_prueba' => $cveTipoPrueba]
		);
	}

	public function iniciar(array $data): array
	{
		return $this->insert('evaluacion', $this->filterTableData('evaluacion', $data, ['cve_evaluacion']), 'cve_evaluacion');
	}

	public function responder(string|int $cveEvaluacion, array $data): array
	{
		$data['cve_evaluacion'] = $cveEvaluacion;
		return $this->insert('respuesta_egresado', $this->filterTableData('respuesta_egresado', $data, ['cve_respuesta_egresado']), 'cve_respuesta_egresado');
	}

	public function finalizar(string|int $cveEvaluacion, array $data): ?array
	{
		return $this->updateById('evaluacion', 'cve_evaluacion', $cveEvaluacion, $this->filterTableData('evaluacion', $data, ['cve_evaluacion', 'cve_egresado', 'cve_tipo_prueba']));
	}
}
