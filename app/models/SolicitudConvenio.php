<?php
declare(strict_types=1);

namespace app\models;

class SolicitudConvenio extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll($this->selectSql() . ' ORDER BY sc.cve_solicitud_convenio DESC');
	}

	public function create(array $data): array
	{
		if (empty($data['cve_empresa']) && !empty($data['empresa_nombre'])) {
			$empresa = $this->empresaDesdeSolicitud($data);
			$data['cve_empresa'] = $empresa['cve_empresa'];
		}

		$data['motivo'] = $data['motivo'] ?? 'Solicitud de convenio enviada desde el formulario público.';
		$data['origen'] = $data['origen'] ?? 'frontend';
		$data = $this->normalizarEstado($data);

		$row = $this->insert('solicitud_convenio', $this->filterTableData('solicitud_convenio', $data, ['cve_solicitud_convenio']), 'cve_solicitud_convenio');

		return $this->find($row['cve_solicitud_convenio']) ?? $row;
	}

	public function update(string|int $cveSolicitud, array $data): ?array
	{
		$data = $this->normalizarEstado($data);
		if (in_array($data['estado'] ?? '', ['aprobada', 'rechazada', 'formalizada'], true) && empty($data['fecha_resolucion'])) {
			$data['fecha_resolucion'] = date('c');
		}

		$row = $this->updateById('solicitud_convenio', 'cve_solicitud_convenio', $cveSolicitud, $this->filterTableData('solicitud_convenio', $data, ['cve_solicitud_convenio', 'cve_empresa']));

		return $row === null ? null : ($this->find($cveSolicitud) ?? $row);
	}

	private function find(string|int $cveSolicitud): ?array
	{
		return $this->fetchOne(
			$this->selectSql() . ' WHERE sc.cve_solicitud_convenio = :cve_solicitud_convenio',
			['cve_solicitud_convenio' => $cveSolicitud]
		);
	}

	private function selectSql(): string
	{
		return 'SELECT
				sc.*,
				e.razon_social AS empresa_nombre,
				e.rfc,
				e.zona,
				e.sector AS giro,
				ce.nombre_completo AS contacto_nombre,
				ce.correo AS contacto_email,
				ce.telefono AS contacto_telefono
			FROM solicitud_convenio sc
			JOIN empresa e ON e.cve_empresa = sc.cve_empresa
			LEFT JOIN LATERAL (
				SELECT
					trim(concat_ws(\' \', c.nombre, c.primer_apellido, c.segundo_apellido)) AS nombre_completo,
					c.correo,
					c.telefono
				FROM contacto_empresa c
				WHERE c.cve_empresa = e.cve_empresa
				ORDER BY c.principal DESC, c.cve_contacto_empresa
				LIMIT 1
			) ce ON true';
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function empresaDesdeSolicitud(array $data): array
	{
		$rfc = trim((string) ($data['rfc'] ?? ''));
		if ($rfc !== '') {
			$existing = $this->fetchOne('SELECT * FROM empresa WHERE rfc = :rfc', ['rfc' => $rfc]);
			if ($existing !== null) {
				$this->crearOActualizarContacto($existing['cve_empresa'], $data);
				return $existing;
			}
		}

		$nombre = trim((string) $data['empresa_nombre']);
		$existing = $this->fetchOne('SELECT * FROM empresa WHERE razon_social = :razon_social', ['razon_social' => $nombre]);
		if ($existing !== null) {
			$this->crearOActualizarContacto($existing['cve_empresa'], $data);
			return $existing;
		}

		$empresa = $this->insert('empresa', [
			'razon_social' => $nombre,
			'nombre_comercial' => $data['nombre_comercial'] ?? $nombre,
			'rfc' => $rfc !== '' ? $rfc : null,
			'sector' => $data['giro'] ?? null,
			'correo_general' => $data['contacto_email'] ?? null,
			'telefono_general' => $data['contacto_telefono'] ?? null,
			'zona' => $this->normalizarZona((string) ($data['zona'] ?? 'nacional')),
		], 'cve_empresa');

		$this->crearOActualizarContacto($empresa['cve_empresa'], $data);

		return $empresa;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function crearOActualizarContacto(string|int $cveEmpresa, array $data): void
	{
		$correo = trim((string) ($data['contacto_email'] ?? ''));
		$nombreCompleto = trim((string) ($data['contacto_nombre'] ?? ''));
		if ($correo === '' || $nombreCompleto === '') {
			return;
		}

		$partes = preg_split('/\s+/', $nombreCompleto) ?: [];
		$nombre = array_shift($partes) ?: 'Contacto';
		$primerApellido = array_shift($partes) ?: 'Empresa';
		$segundoApellido = trim(implode(' ', $partes)) ?: null;

		$this->fetchOne(
			'INSERT INTO contacto_empresa (
				cve_empresa, nombre, primer_apellido, segundo_apellido, correo, telefono, cargo, principal
			) VALUES (
				:cve_empresa, :nombre, :primer_apellido, :segundo_apellido, :correo, :telefono, :cargo, true
			)
			ON CONFLICT (cve_empresa, correo) DO UPDATE SET
				nombre = EXCLUDED.nombre,
				primer_apellido = EXCLUDED.primer_apellido,
				segundo_apellido = EXCLUDED.segundo_apellido,
				telefono = EXCLUDED.telefono,
				cargo = EXCLUDED.cargo,
				principal = true
			RETURNING *',
			[
				'cve_empresa' => $cveEmpresa,
				'nombre' => $nombre,
				'primer_apellido' => $primerApellido,
				'segundo_apellido' => $segundoApellido,
				'correo' => $correo,
				'telefono' => $data['contacto_telefono'] ?? null,
				'cargo' => $data['contacto_cargo'] ?? 'Contacto de convenio',
			]
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function normalizarEstado(array $data): array
	{
		if (($data['estado'] ?? null) === 'en_proceso') {
			$data['estado'] = 'en_revision';
		}

		return $data;
	}

	private function normalizarZona(string $zona): string
	{
		return in_array($zona, ['norte', 'norte_nayarit'], true) ? 'norte_nayarit' : 'nacional';
	}
}
