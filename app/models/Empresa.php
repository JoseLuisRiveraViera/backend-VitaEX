<?php
declare(strict_types=1);

namespace app\models;

class Empresa extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll('SELECT * FROM empresa ORDER BY cve_empresa DESC');
	}

	public function find(string|int $cveEmpresa): ?array
	{
		return $this->fetchOne('SELECT * FROM empresa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
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
		return $this->fetchAll('SELECT * FROM vista_vacante_completa WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
	}

	public function candidatos(string|int $cveEmpresa): array
	{
		return $this->fetchAll('SELECT * FROM vista_candidato_idoneo WHERE cve_empresa = :cve_empresa', ['cve_empresa' => $cveEmpresa]);
	}
}
