<?php
declare(strict_types=1);

namespace app\models;

class Vacante extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll('SELECT * FROM vista_vacante_completa');
	}

	public function find(string|int $cveVacante): ?array
	{
		return $this->fetchOne('SELECT * FROM vista_vacante_completa WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
	}

	public function create(array $data): array
	{
		$perfil = $data['perfil_idoneo'] ?? [];
		unset($data['perfil_idoneo']);

		$this->db->beginTransaction();
		try {
			$vacante = $this->insert('vacante', $this->filterTableData('vacante', $data, ['cve_vacante']), 'cve_vacante');

			if (is_array($perfil) && $perfil !== []) {
				$perfil['cve_vacante'] = $vacante['cve_vacante'];
				$this->insert('perfil_idoneo', $this->filterTableData('perfil_idoneo', $perfil, ['cve_perfil_idoneo']), 'cve_perfil_idoneo');
			}

			$this->db->commit();
			return $vacante;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function update(string|int $cveVacante, array $data): ?array
	{
		unset($data['perfil_idoneo']);
		return $this->updateById('vacante', 'cve_vacante', $cveVacante, $this->filterTableData('vacante', $data, ['cve_vacante']));
	}

	public function softDelete(string|int $cveVacante): ?array
	{
		return $this->fetchOne('UPDATE vacante SET activo = false WHERE cve_vacante = :cve_vacante RETURNING *', ['cve_vacante' => $cveVacante]);
	}

	public function candidatos(string|int $cveVacante): array
	{
		return $this->fetchAll('SELECT * FROM vista_candidato_idoneo WHERE cve_vacante = :cve_vacante', ['cve_vacante' => $cveVacante]);
	}

	public function crearPerfilIdoneo(string|int $cveVacante, array $data): array
	{
		$data['cve_vacante'] = $cveVacante;
		return $this->insert('perfil_idoneo', $this->filterTableData('perfil_idoneo', $data, ['cve_perfil_idoneo']), 'cve_perfil_idoneo');
	}
}
