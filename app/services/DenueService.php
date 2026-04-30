<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use InvalidArgumentException;
use RuntimeException;

class DenueService
{
	private const DEFAULT_BASE_URL = 'https://www.inegi.org.mx/app/api/denue/v1/consulta/Buscar';
	private const DEFAULT_RADIUS = 5000;
	private const MAX_RADIUS = 5000;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private const CAREER_MAP = [
		'sistemas' => [
			'carrera' => 'Ing. Sistemas / IT',
			'keyword' => 'computo',
			'scian' => '541510',
			'actividad' => 'Desarrollo de software',
			'aliases' => [
				'ing sistemas',
				'ingenieria en sistemas',
				'sistemas',
				'it',
				'ti',
				'tic',
				'software',
				'computacion',
				'tecnologias de la informacion',
			],
		],
		'arquitectura' => [
			'carrera' => 'Arquitectura',
			'keyword' => 'arquitectura',
			'scian' => '541310',
			'actividad' => 'Servicios de arquitectura',
			'aliases' => [
				'arquitectura',
				'arquitecto',
			],
		],
		'psicologia' => [
			'carrera' => 'Psicologia',
			'keyword' => 'psicologia',
			'scian' => '621330',
			'actividad' => 'Consultorios de psicologia',
			'aliases' => [
				'psicologia',
				'psicologo',
			],
		],
		'contaduria' => [
			'carrera' => 'Contaduria',
			'keyword' => 'contabilidad',
			'scian' => '541211',
			'actividad' => 'Auditoria y contabilidad',
			'aliases' => [
				'contaduria',
				'contador',
				'contabilidad',
				'auditoria',
			],
		],
		'industrial' => [
			'carrera' => 'Ing. Industrial',
			'keyword' => 'manufactura',
			'scian' => '31-33',
			'actividad' => 'Sector manufacturero',
			'aliases' => [
				'ing industrial',
				'ingenieria industrial',
				'industrial',
				'manufactura',
				'procesos industriales',
			],
		],
	];

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function careerMappings(): array
	{
		return self::CAREER_MAP;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	public function buscar(array $params): array
	{
		$token = trim((string) Env::get('DENUE_API_TOKEN', ''));
		if ($token === '') {
			throw new RuntimeException('Configura DENUE_API_TOKEN en .env antes de consultar INEGI.');
		}

		$mapping = null;
		$condition = $this->stringParam($params, ['condicion', 'palabra_clave', 'scian']);
		$career = $this->stringParam($params, ['carrera']);

		if ($career !== null) {
			$mapping = $this->resolveCareer($career);
			$mode = $this->normalizeSearchMode($this->stringParam($params, ['tipo_busqueda', 'modo_busqueda']) ?? Env::get('DENUE_DEFAULT_SEARCH_MODE', 'scian'));
			$condition = (string) $mapping[$mode];
		}

		if ($condition === null || trim($condition) === '') {
			throw new InvalidArgumentException('Indica condicion, palabra_clave, scian o carrera.');
		}

		$latitud = $this->numericParam($params, ['latitud', 'lat'], Env::get('DENUE_DEFAULT_LATITUDE'));
		$longitud = $this->numericParam($params, ['longitud', 'lon', 'lng'], Env::get('DENUE_DEFAULT_LONGITUDE'));
		if ($latitud === null || $longitud === null) {
			throw new InvalidArgumentException('Indica latitud y longitud, o configura DENUE_DEFAULT_LATITUDE y DENUE_DEFAULT_LONGITUDE en .env.');
		}

		$radio = $this->intParam($params, ['radio'], Env::get('DENUE_DEFAULT_RADIUS', (string) self::DEFAULT_RADIUS));
		if ($radio < 1 || $radio > self::MAX_RADIUS) {
			throw new InvalidArgumentException('El radio debe estar entre 1 y ' . self::MAX_RADIUS . ' metros.');
		}

		$includeSmallCompanies = $this->boolParam(
			$params,
			['incluir_micro', 'incluir_pequenas'],
			$this->envBool('DENUE_FILTER_SMALL_COMPANIES', true) === false
		);

		$url = $this->buildUrl($condition, $latitud, $longitud, $radio, $token);
		$rawRows = $this->request($url);
		$filteredRows = $this->filterCompanies($rawRows, $includeSmallCompanies);
		$companies = array_map([$this, 'normalizeCompany'], $filteredRows);

		return [
			'fuente' => [
				'nombre' => 'DENUE INEGI',
				'url_base' => $this->baseUrl(),
			],
			'consulta' => [
				'condicion' => $condition,
				'carrera' => $career,
				'tipo_busqueda' => $career === null ? 'condicion' : ($this->normalizeSearchMode($this->stringParam($params, ['tipo_busqueda', 'modo_busqueda']) ?? Env::get('DENUE_DEFAULT_SEARCH_MODE', 'scian'))),
				'latitud' => $latitud,
				'longitud' => $longitud,
				'radio' => $radio,
				'incluye_microempresas' => $includeSmallCompanies,
			],
			'mapeo_carrera' => $mapping,
			'total_original' => count($rawRows),
			'total_filtrado' => count($companies),
			'empresas' => $companies,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function resolveCareer(string $career): array
	{
		$normalizedCareer = $this->normalizeText($career);

		foreach (self::CAREER_MAP as $key => $mapping) {
			$names = array_merge(
				[$key, (string) $mapping['carrera']],
				is_array($mapping['aliases']) ? $mapping['aliases'] : []
			);

			foreach ($names as $name) {
				$normalizedName = $this->normalizeText((string) $name);
				if ($normalizedCareer === $normalizedName || str_contains($normalizedCareer, $normalizedName)) {
					return $mapping;
				}
			}
		}

		throw new InvalidArgumentException('Carrera no mapeada para DENUE: ' . $career . '. Consulta /api/denue/carreras.');
	}

	/**
	 * @param array<string, mixed> $company
	 * @return array<string, mixed>
	 */
	public function normalizeCompany(array $company): array
	{
		$location = $this->parseLocation($company);
		$name = $this->field($company, ['Nombre', 'nombre']);
		$businessName = $this->field($company, ['Razon_social', 'RazonSocial', 'razon_social']);

		return [
			'id_externo' => $this->field($company, ['Id', 'id', 'CLEE', 'clee']),
			'nombre' => $name,
			'razon_social' => $businessName !== null ? $businessName : $name,
			'actividad' => $this->field($company, ['Clase_actividad', 'ClaseActividad', 'clase_actividad']),
			'estrato' => $this->field($company, ['Estrato', 'estrato']),
			'tipo' => $this->field($company, ['Tipo', 'tipo']),
			'telefono' => $this->field($company, ['Telefono', 'telefono']),
			'correo' => $this->field($company, ['Correo_e', 'Correo', 'correo']),
			'sitio_web' => $this->field($company, ['Sitio_internet', 'SitioInternet', 'sitio_web']),
			'direccion' => $this->formatAddress($company),
			'ubicacion_texto' => $this->field($company, ['Ubicacion', 'ubicacion']),
			'estado' => $location['estado'],
			'municipio' => $location['municipio'],
			'localidad' => $location['localidad'],
			'codigo_postal' => $this->field($company, ['CP', 'Codigo_postal', 'codigo_postal']),
			'latitud' => $this->floatField($company, ['Latitud', 'latitud']),
			'longitud' => $this->floatField($company, ['Longitud', 'longitud']),
			'fuente' => 'DENUE',
			'datos_originales' => $company,
		];
	}

	private function buildUrl(string $condition, float $latitud, float $longitud, int $radio, string $token): string
	{
		return rtrim($this->baseUrl(), '/')
			. '/'
			. rawurlencode($condition)
			. '/'
			. $this->coordinate($latitud)
			. ','
			. $this->coordinate($longitud)
			. '/'
			. $radio
			. '/'
			. rawurlencode($token);
	}

	private function baseUrl(): string
	{
		return rtrim((string) Env::get('DENUE_API_BASE_URL', self::DEFAULT_BASE_URL), '/');
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function request(string $url): array
	{
		$timeout = max(1, (int) Env::get('DENUE_TIMEOUT_SECONDS', '15'));
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => $timeout,
				'ignore_errors' => true,
				'header' => implode("\r\n", [
					'Accept: application/json',
					'User-Agent: backend-VitaEX/1.0',
				]),
			],
		]);

		$response = @file_get_contents($url, false, $context);
		$headers = $http_response_header ?? [];
		$status = $this->statusCode($headers);

		if ($response === false) {
			$error = error_get_last();
			throw new RuntimeException('No se pudo conectar con la API DENUE: ' . ($error['message'] ?? 'error desconocido'));
		}

		if ($status >= 400) {
			throw new RuntimeException('La API DENUE respondio con HTTP ' . $status . '.');
		}

		$data = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE || is_array($data) === false) {
			throw new RuntimeException('La API DENUE no devolvio JSON valido.');
		}

		if (array_is_list($data) === false && isset($data['error'])) {
			throw new RuntimeException('La API DENUE devolvio error: ' . (string) $data['error']);
		}

		if (array_is_list($data) === false) {
			return [];
		}

		return array_values(array_filter($data, static fn($row): bool => is_array($row)));
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private function filterCompanies(array $rows, bool $includeSmallCompanies): array
	{
		if ($includeSmallCompanies === true) {
			return $rows;
		}

		return array_values(array_filter($rows, function (array $row): bool {
			$estrato = $this->normalizeText($this->field($row, ['Estrato', 'estrato']) ?? '');
			return $estrato !== '0 a 5 personas';
		}));
	}

	/**
	 * @param array<string, mixed> $company
	 * @return array{estado: ?string, municipio: ?string, localidad: ?string}
	 */
	private function parseLocation(array $company): array
	{
		$state = $this->field($company, ['Entidad', 'Estado', 'estado']);
		$municipality = $this->field($company, ['Municipio', 'municipio']);
		$locality = $this->field($company, ['Localidad', 'localidad']);

		if ($state !== null && $municipality !== null) {
			return [
				'estado' => $this->title($state),
				'municipio' => $this->title($municipality),
				'localidad' => $locality === null ? null : $this->title($locality),
			];
		}

		$location = $this->field($company, ['Ubicacion', 'ubicacion']);
		if ($location === null) {
			return [
				'estado' => $state === null ? null : $this->title($state),
				'municipio' => $municipality === null ? null : $this->title($municipality),
				'localidad' => $locality === null ? null : $this->title($locality),
			];
		}

		$parts = array_values(array_filter(array_map('trim', explode(',', $location)), static fn(string $part): bool => $part !== ''));
		$count = count($parts);

		if ($count >= 3) {
			return [
				'estado' => $this->title($parts[$count - 1]),
				'municipio' => $this->title($parts[$count - 2]),
				'localidad' => $this->title($parts[0]),
			];
		}

		if ($count === 2) {
			return [
				'estado' => $this->title($parts[1]),
				'municipio' => $this->title($parts[0]),
				'localidad' => null,
			];
		}

		return [
			'estado' => $state === null ? null : $this->title($state),
			'municipio' => $municipality === null ? null : $this->title($municipality),
			'localidad' => $locality === null ? null : $this->title($locality),
		];
	}

	/**
	 * @param array<string, mixed> $company
	 */
	private function formatAddress(array $company): ?string
	{
		$parts = array_filter([
			$this->field($company, ['Tipo_vialidad', 'tipo_vialidad']),
			$this->field($company, ['Calle', 'calle']),
			$this->field($company, ['Num_Exterior', 'num_exterior']),
			$this->field($company, ['Num_Interior', 'num_interior']),
			$this->field($company, ['Colonia', 'colonia']),
			$this->field($company, ['CP', 'codigo_postal']),
			$this->field($company, ['Ubicacion', 'ubicacion']),
		], static fn(?string $value): bool => $value !== null && $value !== '');

		return $parts === [] ? null : implode(', ', $parts);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $keys
	 */
	private function stringParam(array $data, array $keys): ?string
	{
		foreach ($keys as $key) {
			if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
				return trim((string) $data[$key]);
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $keys
	 */
	private function numericParam(array $data, array $keys, ?string $default): ?float
	{
		$value = $this->stringParam($data, $keys) ?? $default;
		if ($value === null || trim($value) === '') {
			return null;
		}

		if (is_numeric($value) === false) {
			throw new InvalidArgumentException('Coordenada invalida: ' . $value);
		}

		return (float) $value;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $keys
	 */
	private function intParam(array $data, array $keys, ?string $default): int
	{
		$value = $this->stringParam($data, $keys) ?? $default ?? (string) self::DEFAULT_RADIUS;
		if (ctype_digit((string) $value) === false) {
			throw new InvalidArgumentException('Radio invalido: ' . $value);
		}

		return (int) $value;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $keys
	 */
	private function boolParam(array $data, array $keys, bool $default): bool
	{
		$value = $this->stringParam($data, $keys);
		if ($value === null) {
			return $default;
		}

		return in_array($this->normalizeText($value), ['1', 'true', 'si', 'yes', 'on'], true);
	}

	private function envBool(string $key, bool $default): bool
	{
		$value = Env::get($key);
		if ($value === null || trim($value) === '') {
			return $default;
		}

		return in_array($this->normalizeText($value), ['1', 'true', 'si', 'yes', 'on'], true);
	}

	private function normalizeSearchMode(?string $mode): string
	{
		$normalized = $this->normalizeText($mode ?? 'scian');
		if (in_array($normalized, ['keyword', 'palabra', 'palabra clave', 'palabra_clave'], true)) {
			return 'keyword';
		}

		if ($normalized === 'scian') {
			return 'scian';
		}

		throw new InvalidArgumentException('tipo_busqueda debe ser scian o keyword.');
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $keys
	 */
	private function field(array $data, array $keys): ?string
	{
		foreach ($keys as $key) {
			if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
				return trim((string) $data[$key]);
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $keys
	 */
	private function floatField(array $data, array $keys): ?float
	{
		$value = $this->field($data, $keys);
		return $value !== null && is_numeric($value) ? (float) $value : null;
	}

	/**
	 * @param list<string> $headers
	 */
	private function statusCode(array $headers): int
	{
		$first = $headers[0] ?? '';
		if (preg_match('/\s(\d{3})\s/', $first, $matches) !== 1) {
			return 200;
		}

		return (int) $matches[1];
	}

	private function coordinate(float $value): string
	{
		return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
	}

	private function normalizeText(string $text): string
	{
		$text = trim($text);
		$text = strtr($text, [
			'Á' => 'A',
			'É' => 'E',
			'Í' => 'I',
			'Ó' => 'O',
			'Ú' => 'U',
			'Ü' => 'U',
			'Ñ' => 'N',
			'á' => 'a',
			'é' => 'e',
			'í' => 'i',
			'ó' => 'o',
			'ú' => 'u',
			'ü' => 'u',
			'ñ' => 'n',
		]);
		$text = strtolower($text);
		$text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
		return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
	}

	private function title(string $text): string
	{
		return ucwords(strtolower(trim($text)));
	}
}
