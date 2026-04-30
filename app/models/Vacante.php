<?php
declare(strict_types=1);

namespace app\models;

class Vacante extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];

		if (!empty($query['search'])) {
			$where[] = '(titulo ILIKE :search OR descripcion ILIKE :search OR razon_social ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		foreach (['area', 'municipio', 'estado'] as $field) {
			if (!empty($query[$field])) {
				$where[] = $this->identifier($field) . ' = :' . $field;
				$params[$field] = $query[$field];
			}
		}
		if (!empty($query['zona'])) {
			$where[] = 'zona = :zona';
			$params['zona'] = $query['zona'];
		}

		$sqlWhere = implode(' AND ', $where);
		$result = $this->paginate(
			'SELECT * FROM vw_vacante_completa WHERE ' . $sqlWhere . ' ORDER BY fecha_publicacion DESC, cve_vacante DESC',
			'SELECT COUNT(*) FROM vw_vacante_completa WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
		$result['items'] = $this->anexarPreguntasTecnicas($result['items']);
		return $result;
	}

	public function find(string|int $cveVacante): ?array
	{
		$row = $this->fetchOne('SELECT * FROM vw_vacante_completa WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
		return $row === null ? null : $this->anexarPreguntasTecnicas([$row])[0];
	}

	public function create(array $data): array
	{
		$perfil = $data['perfil_idoneo'] ?? [];
		$preguntas = $this->normalizarPreguntasTecnicas($data['preguntas_tecnicas'] ?? []);
		unset($data['perfil_idoneo'], $data['preguntas_tecnicas']);
		$data = $this->normalizarVacanteData($data, true);

		$this->db->beginTransaction();
		try {
			$vacante = $this->insert('vacante', $this->filterTableData('vacante', $data, ['cve_vacante']), 'cve_vacante');

			if (is_array($perfil) && $perfil !== []) {
				$perfil['cve_vacante'] = $vacante['cve_vacante'];
					$this->insert('perfil_idoneo', $this->filterTableData('perfil_idoneo', $perfil, ['cve_perfil_idoneo']), 'cve_perfil_idoneo');
				}
				$this->sincronizarPreguntasTecnicas($vacante['cve_vacante'], $preguntas);

				$this->db->commit();
				return $this->anexarPreguntasTecnicas([$vacante])[0];
			} catch (\Throwable $exception) {
				$this->db->rollBack();
				throw $exception;
			}
	}

	public function update(string|int $cveVacante, array $data): ?array
	{
		$hasPreguntas = array_key_exists('preguntas_tecnicas', $data);
		$preguntas = $hasPreguntas ? $this->normalizarPreguntasTecnicas($data['preguntas_tecnicas']) : [];
		unset($data['perfil_idoneo'], $data['preguntas_tecnicas']);
		$data = $this->normalizarVacanteData($data, false);

		$this->db->beginTransaction();
		try {
			$row = $data === []
				? $this->fetchOne('SELECT * FROM vacante WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante])
				: $this->updateById('vacante', 'cve_vacante', $cveVacante, $this->filterTableData('vacante', $data, ['cve_vacante']));
			if ($row !== null && $hasPreguntas) {
				$this->sincronizarPreguntasTecnicas($cveVacante, $preguntas);
			}
			$this->db->commit();
			return $row === null ? null : $this->anexarPreguntasTecnicas([$row])[0];
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function softDelete(string|int $cveVacante): ?array
	{
		return $this->fetchOne('UPDATE vacante SET estado = :estado WHERE cve_vacante = :cve_vacante RETURNING *', [
			'estado' => 'cancelada',
			'cve_vacante' => $cveVacante,
		]);
	}

	public function candidatos(string|int $cveVacante): array
	{
		return $this->fetchAll(
			'SELECT c.*,
				pe.carrera,
				pe.matricula,
				pe.correo_institucional,
				pe.correo_personal,
				pe.telefono,
				pe.url_cv,
				pe.url_foto,
				pe.anio_egreso,
				punt.puntaje_psicometrica,
				punt.puntaje_cognitiva,
				punt.puntaje_tecnica,
				punt.puntaje_proyectiva
			FROM vw_dashboard_candidato_idoneo c
			LEFT JOIN vw_perfil_completo_egresado pe ON pe.cve_egresado = c.cve_egresado
			LEFT JOIN vw_puntaje_egresado punt ON punt.cve_egresado = c.cve_egresado
			WHERE c.cve_vacante = :cve_vacante
			ORDER BY c.porcentaje_coincidencia DESC, c.fecha_postulacion DESC',
			['cve_vacante' => $cveVacante]
		);
	}

	public function crearPerfilIdoneo(string|int $cveVacante, array $data): array
	{
		$data['cve_vacante'] = $cveVacante;
		return $this->insert('perfil_idoneo', $this->filterTableData('perfil_idoneo', $data, ['cve_perfil_idoneo']), 'cve_perfil_idoneo');
	}

	public function actualizarPerfilIdoneo(string|int $cveVacante, array $data): ?array
	{
		$current = $this->fetchOne('SELECT * FROM perfil_idoneo WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
		if ($current === null) {
			return $this->crearPerfilIdoneo($cveVacante, $data);
		}

		return $this->updateById('perfil_idoneo', 'cve_perfil_idoneo', $current['cve_perfil_idoneo'], $this->filterTableData('perfil_idoneo', $data, ['cve_perfil_idoneo', 'cve_vacante']));
	}

	public function anexarPreguntasTecnicas(array $rows): array
	{
		if ($rows === []) {
			return [];
		}

		$this->ensurePreguntasTecnicasTable();
		$ids = array_values(array_filter(array_map(
			static fn(array $row): string => (string) ($row['cve_vacante'] ?? ''),
			$rows
		)));
		if ($ids === []) {
			return $rows;
		}

		$placeholders = [];
		$params = [];
		foreach ($ids as $index => $id) {
			$key = 'id' . $index;
			$placeholders[] = ':' . $key;
			$params[$key] = $id;
		}

		$preguntas = $this->fetchAll(
			'SELECT cve_vacante, texto
			FROM vacante_pregunta_tecnica
			WHERE estado = :estado
			  AND cve_vacante IN (' . implode(',', $placeholders) . ')
			ORDER BY cve_vacante, orden, cve_vacante_pregunta_tecnica',
			array_merge(['estado' => 'activa'], $params)
		);
		$perfiles = $this->fetchAll(
			'SELECT cve_vacante,
				peso_psicometrica,
				peso_cognitiva,
				peso_tecnica,
				peso_proyectiva
			FROM perfil_idoneo
			WHERE cve_vacante IN (' . implode(',', $placeholders) . ')',
			$params
		);

		$porVacante = [];
		foreach ($preguntas as $pregunta) {
			$porVacante[(string) $pregunta['cve_vacante']][] = $pregunta['texto'];
		}
		$perfilPorVacante = [];
		foreach ($perfiles as $perfil) {
			$perfilPorVacante[(string) $perfil['cve_vacante']] = $perfil;
		}

		return array_map(static function (array $row) use ($porVacante, $perfilPorVacante): array {
			$id = (string) ($row['cve_vacante'] ?? '');
			$row['preguntas_tecnicas'] = $porVacante[$id] ?? [];
			if (isset($perfilPorVacante[$id])) {
				$row = array_merge($row, $perfilPorVacante[$id]);
			}
			return $row;
		}, $rows);
	}

	private function normalizarVacanteData(array $data, bool $creating): array
	{
		if (!empty($data['ubicacion']) && empty($data['cve_ubicacion'])) {
			$data['cve_ubicacion'] = $this->resolverUbicacion((string) $data['ubicacion']);
		}
		unset($data['ubicacion']);

		if (!empty($data['salario_rango']) && empty($data['salario_minimo']) && empty($data['salario_maximo'])) {
			[$minimo, $maximo] = $this->parseSalario((string) $data['salario_rango']);
			if ($minimo !== null) {
				$data['salario_minimo'] = $minimo;
			}
			if ($maximo !== null) {
				$data['salario_maximo'] = $maximo;
			}
		}
		unset($data['salario_rango']);

		if ($creating && empty($data['fecha_publicacion'])) {
			$data['fecha_publicacion'] = date('Y-m-d');
		}

		return $data;
	}

	private function resolverUbicacion(string $ubicacion): int
	{
		$parts = array_values(array_filter(array_map('trim', explode(',', $ubicacion)), static fn(string $value): bool => $value !== ''));
		$estado = $parts[count($parts) - 1] ?? 'Nayarit';
		$municipio = $parts[count($parts) - 2] ?? ($parts[0] ?? 'No especificado');
		$localidad = $parts[count($parts) - 3] ?? null;

		$params = [
			'estado' => $estado,
			'municipio' => $municipio,
			'localidad' => $localidad,
		];
		$current = $this->fetchOne(
			'SELECT *
			FROM ubicacion
			WHERE estado = :estado
			  AND municipio = :municipio
			  AND COALESCE(localidad, \'\') = COALESCE(:localidad, \'\')
			ORDER BY cve_ubicacion
			LIMIT 1',
			$params
		);
		if ($current !== null) {
			return (int) $current['cve_ubicacion'];
		}

		$nueva = $this->insert('ubicacion', [
			'pais' => 'México',
			'estado' => $estado,
			'municipio' => $municipio,
			'localidad' => $localidad,
		], 'cve_ubicacion');

		return (int) $nueva['cve_ubicacion'];
	}

	private function parseSalario(string $salario): array
	{
		$clean = str_replace([',', '$'], '', $salario);
		preg_match_all('/\d+(?:\.\d+)?/', $clean, $matches);
		$values = array_map('floatval', $matches[0] ?? []);
		if ($values === []) {
			return [null, null];
		}
		if (count($values) === 1) {
			return [$values[0], $values[0]];
		}
		return [min($values[0], $values[1]), max($values[0], $values[1])];
	}

	private function normalizarPreguntasTecnicas(mixed $preguntas): array
	{
		if (is_array($preguntas) === false) {
			return [];
		}

		$limpias = [];
		foreach ($preguntas as $pregunta) {
			$texto = is_array($pregunta) ? ($pregunta['texto'] ?? '') : $pregunta;
			$texto = trim((string) $texto);
			if ($texto !== '') {
				$limpias[] = $texto;
			}
		}

		return array_values(array_unique($limpias));
	}

	private function sincronizarPreguntasTecnicas(string|int $cveVacante, array $preguntas): void
	{
		$this->ensurePreguntasTecnicasTable();
		$this->execute('DELETE FROM vacante_pregunta_tecnica WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);

		foreach ($preguntas as $index => $texto) {
			$this->insert('vacante_pregunta_tecnica', [
				'cve_vacante' => $cveVacante,
				'texto' => $texto,
				'orden' => $index + 1,
				'estado' => 'activa',
			], 'cve_vacante_pregunta_tecnica');
		}
	}

	private function ensurePreguntasTecnicasTable(): void
	{
		$this->execute(
			'CREATE TABLE IF NOT EXISTS vacante_pregunta_tecnica (
				cve_vacante_pregunta_tecnica BIGSERIAL PRIMARY KEY,
				cve_vacante BIGINT NOT NULL REFERENCES vacante(cve_vacante) ON DELETE CASCADE,
				texto TEXT NOT NULL,
				orden INTEGER NOT NULL DEFAULT 1,
				estado VARCHAR(20) NOT NULL DEFAULT \'activa\',
				fecha_registro TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
				fecha_modificacion TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
			)'
		);
	}
}
