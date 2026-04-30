<?php
declare(strict_types=1);

namespace app\models;

class Certificado extends BaseModel
{
	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM documento_egresado WHERE cve_egresado = :cve_egresado ORDER BY cve_documento_egresado DESC', ['cve_egresado' => $cveEgresado]);
	}

	public function create(string|int $cveEgresado, array $data): array
	{
		$data['cve_egresado'] = $cveEgresado;
		return $this->insert('documento_egresado', $this->filterTableData('documento_egresado', $data, ['cve_documento_egresado']), 'cve_documento_egresado');
	}

	public function update(string|int $id, array $data): ?array
	{
		return $this->updateById('documento_egresado', 'cve_documento_egresado', $id, $this->filterTableData('documento_egresado', $data, ['cve_documento_egresado', 'cve_egresado']));
	}

	public function delete(string|int $id): int
	{
		return $this->execute('DELETE FROM documento_egresado WHERE cve_documento_egresado = :id', ['id' => $id]);
	}

	public function validar(string|int $id): ?array
	{
		return $this->fetchOne('UPDATE documento_egresado SET verificado = true WHERE cve_documento_egresado = :id RETURNING *', ['id' => $id]);
	}
}
