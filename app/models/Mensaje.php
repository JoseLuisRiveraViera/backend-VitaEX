<?php
declare(strict_types=1);

namespace app\models;

class Mensaje extends BaseModel
{
	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll('SELECT * FROM mensaje WHERE cve_egresado = :cve_egresado ORDER BY cve_mensaje DESC', ['cve_egresado' => $cveEgresado]);
	}

	public function porEmpresa(string|int $cveEmpresa): array
	{
		return $this->fetchAll('SELECT * FROM mensaje WHERE cve_empresa = :cve_empresa ORDER BY cve_mensaje DESC', ['cve_empresa' => $cveEmpresa]);
	}

	public function create(array $data): array
	{
		return $this->insert('mensaje', $this->filterTableData('mensaje', $data, ['cve_mensaje']), 'cve_mensaje');
	}

	public function marcarLeido(string|int $cveMensaje): ?array
	{
		return $this->fetchOne('UPDATE mensaje SET leido = true WHERE cve_mensaje = :cve_mensaje RETURNING *', ['cve_mensaje' => $cveMensaje]);
	}
}
