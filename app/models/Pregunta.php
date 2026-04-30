<?php
declare(strict_types=1);

namespace app\models;

class Pregunta extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll(
			'SELECT p.*, pr.cve_tipo_prueba, pr.cve_empresa
			FROM pregunta p
			JOIN prueba pr ON pr.cve_prueba = p.cve_prueba
			ORDER BY p.cve_pregunta DESC'
		);
	}

	public function find(string|int $id): ?array
	{
		return $this->fetchOne('SELECT * FROM pregunta WHERE cve_pregunta = :id', ['id' => $id]);
	}

	public function create(array $data): array
	{
		$opciones = $data['opciones'] ?? [];
		unset($data['opciones']);

		if (empty($data['cve_prueba']) && !empty($data['cve_tipo_prueba'])) {
			$data['cve_prueba'] = $this->ensurePrueba($data);
		}
		if (isset($data['texto_pregunta']) && empty($data['texto'])) {
			$data['texto'] = $data['texto_pregunta'];
		}
		if (isset($data['tipo_reactivo']) && empty($data['tipo_pregunta'])) {
			$data['tipo_pregunta'] = $data['tipo_reactivo'];
		}
		unset($data['cve_tipo_prueba'], $data['cve_empresa'], $data['texto_pregunta'], $data['tipo_reactivo']);

		$this->db->beginTransaction();
		try {
			$pregunta = $this->insert('pregunta', $this->filterTableData('pregunta', $data, ['cve_pregunta']), 'cve_pregunta');
			if (is_array($opciones)) {
				foreach ($opciones as $opcion) {
					if (is_array($opcion) === false) {
						continue;
					}
					if (isset($opcion['texto_opcion']) && empty($opcion['texto'])) {
						$opcion['texto'] = $opcion['texto_opcion'];
					}
					if (isset($opcion['valor_puntaje']) && !isset($opcion['valor'])) {
						$opcion['valor'] = $opcion['valor_puntaje'];
					}
					unset($opcion['texto_opcion'], $opcion['valor_puntaje']);
					$opcion['cve_pregunta'] = $pregunta['cve_pregunta'];
					$this->insert('opcion_respuesta', $this->filterTableData('opcion_respuesta', $opcion, ['cve_opcion_respuesta']), 'cve_opcion_respuesta');
				}
			}
			$this->db->commit();
			return $pregunta;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function update(string|int $id, array $data): ?array
	{
		unset($data['opciones']);
		return $this->updateById('pregunta', 'cve_pregunta', $id, $this->filterTableData('pregunta', $data, ['cve_pregunta', 'cve_prueba']));
	}

	public function softDelete(string|int $id): ?array
	{
		return $this->fetchOne('UPDATE pregunta SET estado = :estado WHERE cve_pregunta = :id RETURNING *', ['estado' => 'inactivo', 'id' => $id]);
	}

	public function tecnicasEmpresa(string|int $cveEmpresa): array
	{
		return $this->fetchAll(
			'SELECT p.*
			FROM pregunta p
			JOIN prueba pr ON pr.cve_prueba = p.cve_prueba
			JOIN tipo_prueba tp ON tp.cve_tipo_prueba = pr.cve_tipo_prueba
			WHERE pr.cve_empresa = :cve_empresa AND tp.categoria = :categoria
			ORDER BY p.cve_pregunta DESC',
			['cve_empresa' => $cveEmpresa, 'categoria' => 'tecnica']
		);
	}

	private function ensurePrueba(array $data): int
	{
		$params = [
			'cve_tipo_prueba' => $data['cve_tipo_prueba'],
			'cve_empresa' => $data['cve_empresa'] ?? null,
		];
		$whereEmpresa = $params['cve_empresa'] === null ? 'cve_empresa IS NULL' : 'cve_empresa = :cve_empresa';
		$current = $this->fetchOne(
			'SELECT * FROM prueba WHERE cve_tipo_prueba = :cve_tipo_prueba AND ' . $whereEmpresa . ' ORDER BY cve_prueba LIMIT 1',
			array_filter($params, static fn($value): bool => $value !== null)
		);
		if ($current !== null) {
			return (int) $current['cve_prueba'];
		}

		$prueba = $this->insert('prueba', [
			'cve_tipo_prueba' => $data['cve_tipo_prueba'],
			'cve_empresa' => $data['cve_empresa'] ?? null,
			'nombre' => 'Banco de preguntas',
			'es_banco_general' => empty($data['cve_empresa']),
		], 'cve_prueba');

		return (int) $prueba['cve_prueba'];
	}
}
