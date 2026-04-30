<?php
declare(strict_types=1);

namespace app\models;

class Mensaje extends BaseModel
{
	public function porEgresado(string|int $cveEgresado): array
	{
		return $this->fetchAll(
			'SELECT
				m.*,
				p.cve_egresado,
				p.cve_vacante,
				e.nombre,
				e.primer_apellido,
				e.segundo_apellido,
				v.titulo AS vacante
			FROM mensaje m
			JOIN postulacion p ON p.cve_postulacion = m.cve_postulacion
			JOIN egresado e ON e.cve_egresado = p.cve_egresado
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			WHERE p.cve_egresado = :cve_egresado
			ORDER BY m.cve_mensaje DESC',
			['cve_egresado' => $cveEgresado]
		);
	}

	public function porEmpresa(string|int $cveEmpresa): array
	{
		return $this->fetchAll(
			'SELECT
				m.*,
				p.cve_egresado,
				p.cve_vacante,
				e.nombre,
				e.primer_apellido,
				e.segundo_apellido,
				v.titulo AS vacante
			FROM mensaje m
			JOIN postulacion p ON p.cve_postulacion = m.cve_postulacion
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			JOIN egresado e ON e.cve_egresado = p.cve_egresado
			WHERE v.cve_empresa = :cve_empresa
			ORDER BY m.cve_mensaje DESC',
			['cve_empresa' => $cveEmpresa]
		);
	}

	public function porVacante(string|int $cveVacante): array
	{
		return $this->fetchAll(
			'SELECT
				m.*,
				p.cve_egresado,
				p.cve_vacante,
				e.nombre,
				e.primer_apellido,
				e.segundo_apellido,
				v.titulo AS vacante
			FROM mensaje m
			JOIN postulacion p ON p.cve_postulacion = m.cve_postulacion
			JOIN egresado e ON e.cve_egresado = p.cve_egresado
			JOIN vacante v ON v.cve_vacante = p.cve_vacante
			WHERE p.cve_vacante = :cve_vacante
			ORDER BY m.cve_mensaje DESC',
			['cve_vacante' => $cveVacante]
		);
	}

	public function create(array $data): array
	{
		if (empty($data['cve_postulacion']) && !empty($data['cve_egresado']) && !empty($data['cve_vacante'])) {
			$postulacion = $this->fetchOne(
				'SELECT * FROM postulacion WHERE cve_egresado = :cve_egresado AND cve_vacante = :cve_vacante',
				['cve_egresado' => $data['cve_egresado'], 'cve_vacante' => $data['cve_vacante']]
			);
			if ($postulacion !== null) {
				$data['cve_postulacion'] = $postulacion['cve_postulacion'];
			} else {
				$nueva = $this->insert('postulacion', [
					'cve_egresado' => $data['cve_egresado'],
					'cve_vacante' => $data['cve_vacante'],
				], 'cve_postulacion');
				$data['cve_postulacion'] = $nueva['cve_postulacion'];
			}
		}
		if (isset($data['remitente']) && empty($data['tipo_emisor'])) {
			$data['tipo_emisor'] = $data['remitente'];
		}
		if (isset($data['contenido']) && empty($data['mensaje'])) {
			$data['mensaje'] = $data['contenido'];
		}
		unset($data['cve_egresado'], $data['cve_empresa'], $data['cve_vacante'], $data['remitente'], $data['contenido']);
		return $this->insert('mensaje', $this->filterTableData('mensaje', $data, ['cve_mensaje']), 'cve_mensaje');
	}

	public function marcarLeido(string|int $cveMensaje): ?array
	{
		return $this->fetchOne('UPDATE mensaje SET leido = true WHERE cve_mensaje = :cve_mensaje RETURNING *', ['cve_mensaje' => $cveMensaje]);
	}
}
