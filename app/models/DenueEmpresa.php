<?php
declare(strict_types=1);

namespace app\models;

use app\config\Env;

class DenueEmpresa extends BaseModel
{
	/**
	 * @param list<array<string, mixed>> $empresas
	 * @return array<string, mixed>
	 */
	public function importar(array $empresas, ?string $zona = null): array
	{
		$zona = $this->zonaValida($zona ?? Env::get('DENUE_DEFAULT_ZONA', 'norte_nayarit'));
		$resultado = [
			'importadas' => 0,
			'actualizadas' => 0,
			'omitidas' => 0,
			'empresas' => [],
		];

		$this->db->beginTransaction();
		try {
			foreach ($empresas as $empresa) {
				$data = $this->empresaData($empresa, $zona);
				if ($data === null) {
					$resultado['omitidas']++;
					continue;
				}

				$existente = $this->fetchOne(
					'SELECT cve_empresa FROM empresa WHERE razon_social = :razon_social',
					['razon_social' => $data['razon_social']]
				);

				$row = $this->upsertEmpresa($data);
				$existente === null ? $resultado['importadas']++ : $resultado['actualizadas']++;
				$resultado['empresas'][] = $row;
			}

			$this->db->commit();
			return $resultado;
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	/**
	 * @param array<string, mixed> $empresa
	 * @return array<string, mixed>|null
	 */
	private function empresaData(array $empresa, string $zona): ?array
	{
		$razonSocial = $this->clean($empresa['razon_social'] ?? $empresa['Razon_social'] ?? $empresa['Nombre'] ?? $empresa['nombre'] ?? null, 220);
		if ($razonSocial === null) {
			return null;
		}

		return [
			'razon_social' => $razonSocial,
			'nombre_comercial' => $this->clean($empresa['nombre'] ?? $empresa['Nombre'] ?? null, 180),
			'sector' => $this->clean($empresa['actividad'] ?? $empresa['Clase_actividad'] ?? null, 150),
			'sitio_web' => $this->clean($empresa['sitio_web'] ?? $empresa['Sitio_internet'] ?? null, 300),
			'correo_general' => $this->email($empresa['correo'] ?? $empresa['Correo_e'] ?? null),
			'telefono_general' => $this->clean($empresa['telefono'] ?? $empresa['Telefono'] ?? null, 30),
			'cve_ubicacion' => $this->ubicacionId($empresa),
			'zona' => $zona,
			'estado' => 'activo',
		];
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function upsertEmpresa(array $data): array
	{
		$sql = 'INSERT INTO empresa (
				razon_social,
				nombre_comercial,
				sector,
				sitio_web,
				correo_general,
				telefono_general,
				cve_ubicacion,
				zona,
				estado
			) VALUES (
				:razon_social,
				:nombre_comercial,
				:sector,
				:sitio_web,
				:correo_general,
				:telefono_general,
				:cve_ubicacion,
				:zona,
				:estado
			)
			ON CONFLICT (razon_social) DO UPDATE SET
				nombre_comercial = COALESCE(EXCLUDED.nombre_comercial, empresa.nombre_comercial),
				sector = COALESCE(EXCLUDED.sector, empresa.sector),
				sitio_web = COALESCE(EXCLUDED.sitio_web, empresa.sitio_web),
				correo_general = COALESCE(EXCLUDED.correo_general, empresa.correo_general),
				telefono_general = COALESCE(EXCLUDED.telefono_general, empresa.telefono_general),
				cve_ubicacion = COALESCE(EXCLUDED.cve_ubicacion, empresa.cve_ubicacion),
				zona = EXCLUDED.zona,
				estado = EXCLUDED.estado
			RETURNING *';

		$row = $this->fetchOne($sql, $data);
		if ($row === null) {
			throw new \RuntimeException('No se pudo importar la empresa DENUE.');
		}

		return $row;
	}

	/**
	 * @param array<string, mixed> $empresa
	 */
	private function ubicacionId(array $empresa): ?int
	{
		$estado = $this->clean($empresa['estado'] ?? $empresa['Estado'] ?? $empresa['Entidad'] ?? null, 100);
		$municipio = $this->clean($empresa['municipio'] ?? $empresa['Municipio'] ?? null, 100);
		$localidad = $this->clean($empresa['localidad'] ?? $empresa['Localidad'] ?? null, 150);
		$codigoPostal = $this->clean($empresa['codigo_postal'] ?? $empresa['CP'] ?? null, 10);

		if (($estado === null || $municipio === null) && isset($empresa['ubicacion_texto'])) {
			$parsed = $this->parseUbicacion((string) $empresa['ubicacion_texto']);
			$estado = $estado ?? $parsed['estado'];
			$municipio = $municipio ?? $parsed['municipio'];
			$localidad = $localidad ?? $parsed['localidad'];
		}

		if ($estado === null || $municipio === null) {
			return null;
		}

		$params = [
			'pais' => 'Mexico',
			'estado' => $estado,
			'municipio' => $municipio,
			'localidad' => $localidad,
			'codigo_postal' => $codigoPostal,
		];

		$existente = $this->fetchOne(
			'SELECT cve_ubicacion
			FROM ubicacion
			WHERE pais = :pais
			  AND estado = :estado
			  AND municipio = :municipio
			  AND localidad IS NOT DISTINCT FROM :localidad
			  AND codigo_postal IS NOT DISTINCT FROM :codigo_postal
			LIMIT 1',
			$params
		);

		if ($existente !== null) {
			return (int) $existente['cve_ubicacion'];
		}

		$row = $this->fetchOne(
			'INSERT INTO ubicacion (pais, estado, municipio, localidad, codigo_postal)
			VALUES (:pais, :estado, :municipio, :localidad, :codigo_postal)
			RETURNING cve_ubicacion',
			$params
		);

		return $row === null ? null : (int) $row['cve_ubicacion'];
	}

	/**
	 * @return array{estado: ?string, municipio: ?string, localidad: ?string}
	 */
	private function parseUbicacion(string $ubicacion): array
	{
		$parts = array_values(array_filter(array_map('trim', explode(',', $ubicacion)), static fn(string $part): bool => $part !== ''));
		$count = count($parts);

		if ($count >= 3) {
			return [
				'estado' => $this->title($parts[$count - 1], 100),
				'municipio' => $this->title($parts[$count - 2], 100),
				'localidad' => $this->title($parts[0], 150),
			];
		}

		if ($count === 2) {
			return [
				'estado' => $this->title($parts[1], 100),
				'municipio' => $this->title($parts[0], 100),
				'localidad' => null,
			];
		}

		return [
			'estado' => null,
			'municipio' => null,
			'localidad' => null,
		];
	}

	private function clean(mixed $value, int $maxLength): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = trim((string) $value);
		if ($value === '' || in_array(strtolower($value), ['n/a', 'na', 'sin informacion'], true)) {
			return null;
		}

		return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
	}

	private function email(mixed $value): ?string
	{
		$email = $this->clean($value, 180);
		return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
	}

	private function zonaValida(?string $zona): string
	{
		return in_array($zona, ['norte_nayarit', 'nacional'], true) ? $zona : 'norte_nayarit';
	}

	private function title(string $text, int $maxLength): ?string
	{
		$text = ucwords(strtolower(trim($text)));
		return $this->clean($text, $maxLength);
	}
}
