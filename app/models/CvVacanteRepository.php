<?php
declare(strict_types=1);

namespace app\models;

class CvVacanteRepository extends BaseModel
{
	public function findById(int $id): ?array
	{
		$sql = 'SELECT 
					v.*,
					e.razon_social,
					e.nombre_comercial
				FROM vacante v
				JOIN empresa e ON e.cve_empresa = v.cve_empresa
				WHERE v.cve_vacante = :id';
		return $this->fetchOne($sql, ['id' => $id]);
	}

	public function findIdealCompetenciesByVacanteId(int $id): ?array
	{
		$sql = 'SELECT * FROM perfil_idoneo WHERE cve_vacante = :id';
		return $this->fetchOne($sql, ['id' => $id]);
	}
}
