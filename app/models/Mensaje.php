<?php
declare(strict_types=1);

namespace app\models;

class Mensaje extends BaseModel
{
	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll(
			'SELECT m.*
			FROM mensaje m
			JOIN postulacion p ON p.cve_postulacion = m.cve_postulacion
			WHERE p.cve_egresado = :cve_egresado
			ORDER BY m.cve_mensaje DESC',
			['cve_egresado' => $cveEgresado]
		);
	}

	public function porEmpresa(string|int $cveEmpresa): array
	{
		return $this->fetchAll(
			'SELECT m.*
			FROM mensaje m
			JOIN postulacion p ON p.cve_postulacion = m.cve_postulacion
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			WHERE v.cve_empresa = :cve_empresa
			ORDER BY m.cve_mensaje DESC',
			['cve_empresa' => $cveEmpresa]
		);
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
