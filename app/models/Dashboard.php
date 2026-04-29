<?php
declare(strict_types=1);

namespace app\models;

class Dashboard extends BaseModel
{
	public function insercion(): array
	{
		return $this->fetchAll('SELECT * FROM vista_insercion_por_carrera');
	}

	public function convenios(): array
	{
		return $this->fetchAll('SELECT * FROM vista_empresa_convenio');
	}

	public function competencias(): array
	{
		return $this->fetchAll(
			'SELECT tp.*,
				(SELECT COUNT(*) FROM evaluacion e WHERE e.cve_tipo_prueba = tp.cve_tipo_prueba) AS total_evaluaciones
			FROM tipo_prueba tp
			ORDER BY tp.cve_tipo_prueba'
		);
	}

	public function empresa(string|int $cveEmpresa): array
	{
		return [
			'vacantes' => $this->fetchAll('SELECT * FROM vista_vacante_completa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]),
			'candidatos' => $this->fetchAll('SELECT * FROM vista_candidato_idoneo WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]),
		];
	}

	public function egresado(string|int $cveEgresado): array
	{
		return [
			'perfil' => $this->fetchOne('SELECT * FROM vista_perfil_egresado WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]),
			'matching' => $this->fetchAll('SELECT * FROM vista_matching_egresado_vacante WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]),
			'postulaciones' => $this->fetchAll('SELECT * FROM postulacion WHERE cve_egresado = :cve_egresado ORDER BY cve_postulacion DESC', ['cve_egresado' => $cveEgresado]),
		];
	}
}
