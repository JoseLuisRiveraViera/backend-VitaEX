<?php
declare(strict_types=1);

namespace app\models;

class Postulacion extends BaseModel
{
	public function create(string|int $cveEgresado, string|int $cveVacante): array
	{
		return $this->insert('postulacion', [
			'cve_egresado' => $cveEgresado,
			'cve_vacante' => $cveVacante,
		], 'cve_postulacion');
	}

	public function actualizarEstatus(string|int $cvePostulacion, array $data): ?array
	{
		return $this->updateById('postulacion', 'cve_postulacion', $cvePostulacion, $this->filterTableData('postulacion', $data, ['cve_postulacion', 'cve_egresado', 'cve_vacante']));
	}
}
