<?php
declare(strict_types=1);

namespace app\models;

class Dashboard extends BaseModel
{
	public function insercion(): array
	{
		return $this->fetchAll('SELECT * FROM vw_insercion_laboral_por_carrera');
	}

	public function convenios(): array
	{
		$items = $this->fetchAll('SELECT * FROM vw_convenio_por_empresa');
		return [
			'items' => $items,
			'resumen' => [
				'total_empresas' => count(array_unique(array_column($items, 'cve_empresa'))),
				'empresas_con_convenio' => count(array_filter($items, static fn(array $item): bool => $item['cve_convenio'] !== null)),
				'empresas_sin_convenio' => count(array_filter($items, static fn(array $item): bool => $item['cve_convenio'] === null)),
				'norte_nayarit' => count(array_filter($items, static fn(array $item): bool => ($item['zona'] ?? null) === 'norte_nayarit')),
				'nacional' => count(array_filter($items, static fn(array $item): bool => ($item['zona'] ?? null) === 'nacional')),
			],
		];
	}

	public function competencias(): array
	{
		return $this->fetchAll('SELECT * FROM vw_ranking_competencia');
	}

	public function empresa(string|int $cveEmpresa): array
	{
		return [
			'vacantes' => $this->fetchAll('SELECT * FROM vw_vacante_completa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]),
			'analitica_vacantes' => $this->fetchAll(
				'SELECT av.*
				FROM vw_analitica_vacante av
				JOIN vacante v ON v.cve_vacante = av.cve_vacante
				WHERE v.cve_empresa = :cve_empresa',
				['cve_empresa' => $cveEmpresa]
			),
			'candidatos' => $this->fetchAll(
				'SELECT
					p.cve_postulacion,
					e.cve_egresado,
					e.nombre,
					e.primer_apellido,
					e.segundo_apellido,
					v.cve_vacante,
					v.titulo AS vacante,
					emp.razon_social AS empresa,
					p.porcentaje_coincidencia,
					p.estado,
					p.fecha_postulacion
				FROM postulacion p
				JOIN egresado e ON e.cve_egresado = p.cve_egresado
				JOIN vacante v ON v.cve_vacante = p.cve_vacante
				JOIN empresa emp ON emp.cve_empresa = v.cve_empresa
				WHERE v.cve_empresa = :cve_empresa
				  AND p.porcentaje_coincidencia >= 80
				ORDER BY p.porcentaje_coincidencia DESC, p.fecha_postulacion DESC',
				['cve_empresa' => $cveEmpresa]
			),
		];
	}

	public function egresado(string|int $cveEgresado): array
	{
		return [
			'perfil' => $this->fetchOne('SELECT * FROM vw_perfil_completo_egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]),
			'puntajes' => $this->fetchOne('SELECT * FROM vw_puntaje_egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]),
			'matching' => $this->fetchAll('SELECT * FROM vw_matching_egresado_vacante WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]),
			'postulaciones' => $this->fetchAll('SELECT * FROM postulacion WHERE cve_egresado = :cve_egresado ORDER BY cve_postulacion DESC', ['cve_egresado' => $cveEgresado]),
			'certificados' => $this->fetchAll('SELECT * FROM documento_egresado WHERE cve_egresado = :cve_egresado ORDER BY cve_documento_egresado DESC', ['cve_egresado' => $cveEgresado]),
			'mensajes_no_leidos' => $this->fetchAll(
				'SELECT m.*
				FROM mensaje m
				JOIN postulacion p ON p.cve_postulacion = m.cve_postulacion
				WHERE p.cve_egresado = :cve_egresado AND m.leido = false',
				['cve_egresado' => $cveEgresado]
			),
		];
	}

	public function vacantes(): array
	{
		return [
			'total_por_estado' => $this->fetchAll('SELECT estado, COUNT(*) AS total FROM vacante GROUP BY estado ORDER BY estado'),
			'total_por_area' => $this->fetchAll('SELECT area, COUNT(*) AS total FROM vacante GROUP BY area ORDER BY total DESC'),
			'total_por_empresa' => $this->fetchAll(
				'SELECT e.cve_empresa, e.razon_social, COUNT(v.cve_vacante) AS total
				FROM empresa e
				LEFT JOIN vacante v ON v.cve_empresa = e.cve_empresa
				GROUP BY e.cve_empresa, e.razon_social
				ORDER BY total DESC'
			),
		];
	}
}
