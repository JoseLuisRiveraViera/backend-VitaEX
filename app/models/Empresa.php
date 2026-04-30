<?php
declare(strict_types=1);

namespace app\models;

class Empresa extends BaseModel
{
	public function all(array $query = []): array
	{
		$params = [];
		$where = ['1 = 1'];

		if (!empty($query['search'])) {
			$where[] = '(e.razon_social ILIKE :search OR e.nombre_comercial ILIKE :search OR e.rfc ILIKE :search)';
			$params['search'] = '%' . $query['search'] . '%';
		}
		if (!empty($query['zona'])) {
			$where[] = 'e.zona = :zona';
			$params['zona'] = $query['zona'] === 'norte' ? 'norte_nayarit' : $query['zona'];
		}
		if (!empty($query['estado'])) {
			$where[] = 'e.estado = :estado';
			$params['estado'] = $query['estado'];
		}

		$sqlWhere = implode(' AND ', $where);
		return $this->paginate(
			$this->selectSql() . ' WHERE ' . $sqlWhere . ' ORDER BY e.cve_empresa DESC',
			'SELECT COUNT(*) FROM empresa e WHERE ' . $sqlWhere,
			$params,
			(int) ($query['page'] ?? 1),
			(int) ($query['limit'] ?? 10)
		);
	}

	public function find(string|int $cveEmpresa): ?array
	{
		return $this->fetchOne($this->selectSql() . ' WHERE e.cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
	}

	public function findByPersonaExterna(string|int $cvePersona): ?array
	{
		$columns = $this->tableColumns('empresa');
		foreach (['cve_persona', 'cve_persona_externa'] as $column) {
			if (in_array($column, $columns, true)) {
				return $this->fetchOne(
					'SELECT * FROM empresa WHERE ' . $this->identifier($column) . ' = :cve_persona',
					['cve_persona' => (string) $cvePersona]
				);
			}
		}

		return null;
	}

	public function findByLoginIdentifier(string $identifier): ?array
	{
		$identifier = strtolower(trim($identifier));
		if ($identifier === '') {
			return null;
		}

		$byCorreo = $this->findByCorreo($identifier);
		if ($byCorreo !== null) {
			return $byCorreo;
		}

		return $this->findByPersonaExterna($identifier);
	}

	public function findByCorreo(string $correo): ?array
	{
		$correo = strtolower(trim($correo));
		if ($correo === '') {
			return null;
		}

		return $this->fetchOne(
			$this->selectSql() . '
			WHERE e.estado = \'activo\'
			  AND (
				lower(trim(coalesce(e.correo_general, \'\'))) = :correo
				OR EXISTS (
					SELECT 1
					FROM contacto_empresa ce
					WHERE ce.cve_empresa = e.cve_empresa
					  AND ce.estado = \'activo\'
					  AND lower(trim(ce.correo)) = :correo
				)
			  )
			ORDER BY
				CASE
					WHEN lower(trim(coalesce(e.correo_general, \'\'))) = :correo THEN 0
					ELSE 1
				END,
				e.cve_empresa
			LIMIT 1',
			['correo' => $correo]
		);
	}

	public function create(array $data): array
	{
		return $this->insert('empresa', $this->filterTableData('empresa', $data, ['cve_empresa']), 'cve_empresa');
	}

	public function update(string|int $cveEmpresa, array $data): ?array
	{
		return $this->updateById('empresa', 'cve_empresa', $cveEmpresa, $this->filterTableData('empresa', $data, ['cve_empresa']));
	}

	public function vacantes(string|int $cveEmpresa): array
	{
		$rows = $this->fetchAll('SELECT * FROM vw_vacante_completa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
		return (new Vacante())->anexarPreguntasTecnicas($rows);
	}

	public function candidatos(string|int $cveEmpresa): array
	{
		return $this->fetchAll(
			'SELECT
				p.cve_postulacion,
				e.cve_egresado,
					perfil.nombre,
					perfil.primer_apellido,
					perfil.segundo_apellido,
					perfil.carrera,
					perfil.matricula,
					perfil.correo_institucional,
					perfil.correo_personal,
					perfil.telefono,
					perfil.url_cv,
					perfil.url_foto,
					perfil.anio_egreso,
					v.cve_vacante,
					v.titulo AS vacante,
					emp.razon_social AS empresa,
					p.porcentaje_coincidencia,
					p.estado,
					p.fecha_postulacion,
					punt.puntaje_psicometrica,
					punt.puntaje_cognitiva,
					punt.puntaje_tecnica,
					punt.puntaje_proyectiva
				FROM postulacion p
				JOIN egresado e ON e.cve_egresado = p.cve_egresado
				JOIN vacante v ON v.cve_vacante = p.cve_vacante
				JOIN empresa emp ON emp.cve_empresa = v.cve_empresa
				LEFT JOIN vw_perfil_completo_egresado perfil ON perfil.cve_egresado = e.cve_egresado
				LEFT JOIN vw_puntaje_egresado punt ON punt.cve_egresado = e.cve_egresado
			WHERE v.cve_empresa = :cve_empresa
			  AND p.porcentaje_coincidencia >= 80
			ORDER BY p.porcentaje_coincidencia DESC, p.fecha_postulacion DESC',
			['cve_empresa' => $cveEmpresa]
		);
	}

	private function selectSql(): string
	{
		return 'SELECT
				e.*,
				c.cve_convenio,
				c.fecha_inicio AS fecha_convenio,
				c.fecha_fin AS fecha_fin_convenio,
				c.estado AS convenio_estado,
				CASE
					WHEN c.cve_convenio IS NULL THEN \'pendiente\'
					WHEN c.estado = \'por_vencer\' THEN \'por_vencer\'
					WHEN c.estado = \'vencido\' THEN \'inactivo\'
					WHEN c.estado = \'activo\' AND c.fecha_fin < current_date THEN \'inactivo\'
					WHEN c.estado = \'activo\' AND c.fecha_fin <= current_date + interval \'60 days\' THEN \'por_vencer\'
					WHEN c.estado = \'activo\' THEN \'activo\'
					WHEN c.estado = \'pendiente\' THEN \'pendiente\'
					ELSE \'inactivo\'
				END AS estatus_convenio,
				CASE
					WHEN c.cve_convenio IS NULL THEN \'ninguno\'
					WHEN e.zona = \'norte_nayarit\' THEN \'automatico\'
					ELSE \'solicitud\'
				END AS tipo_convenio
			FROM empresa e
			LEFT JOIN LATERAL (
				SELECT *
				FROM convenio cn
				WHERE cn.cve_empresa = e.cve_empresa
				ORDER BY cn.fecha_fin DESC, cn.cve_convenio DESC
				LIMIT 1
			) c ON true';
	}
}
