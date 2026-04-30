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
		return $this->fetchAll('SELECT * FROM vw_convenio_por_empresa');
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
			'matching' => $this->fetchAll('SELECT * FROM vw_matching_egresado_vacante WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]),
			'postulaciones' => $this->fetchAll('SELECT * FROM postulacion WHERE cve_egresado = :cve_egresado ORDER BY cve_postulacion DESC', ['cve_egresado' => $cveEgresado]),
		];
	}
}
