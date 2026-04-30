<?php
declare(strict_types=1);

namespace app\services;

use app\models\BaseModel;

class MatchingService extends BaseModel
{
	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM vw_matching_egresado_vacante WHERE cve_egresado = :cve_egresado', ['cve_egresado' => $cveEgresado]);
	}

	public function candidatosPorVacante(string|int $cveVacante): array
	{
		return $this->fetchAll('SELECT * FROM vw_dashboard_candidato_idoneo WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
	}
}
