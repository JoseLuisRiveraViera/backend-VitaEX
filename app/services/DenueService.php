<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class DenueService
{
	private const CAREER_MAP = [
		'ti' => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'sistemas' => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'software' => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'arquitectura' => ['keyword' => 'arquitectura', 'scian' => '541310', 'job_titles' => ['arquitecto', 'architecture', 'dibujante', 'proyectista', 'revit', 'autocad', 'bim']],
		'psicologia' => ['keyword' => 'psicologia', 'scian' => '621330', 'job_titles' => ['psicologo', 'psicologa', 'recursos humanos', 'reclutador', 'talento humano', 'orientador']],
		'contaduria' => ['keyword' => 'contabilidad', 'scian' => '541211', 'job_titles' => ['contador', 'contable', 'auxiliar contable', 'auditor', 'impuestos', 'nominas']],
		'contabilidad' => ['keyword' => 'contabilidad', 'scian' => '541211', 'job_titles' => ['contador', 'contable', 'auxiliar contable', 'auditor', 'impuestos', 'nominas']],
		'industrial' => ['keyword' => 'manufactura', 'scian' => '31-33', 'job_titles' => ['ingeniero industrial', 'calidad', 'procesos', 'produccion', 'manufactura', 'mejora continua', 'seguridad industrial']],
		'administracion' => ['keyword' => 'administracion', 'scian' => null, 'job_titles' => ['administrador', 'administrativo', 'auxiliar administrativo', 'coordinador administrativo', 'gerente administrativo']],
		'turismo' => ['keyword' => 'hotel', 'scian' => null, 'job_titles' => ['turismo', 'hotel', 'recepcionista', 'reservaciones', 'guest service', 'agente de viajes']],
		'gastronomia' => ['keyword' => 'restaurante', 'scian' => null, 'job_titles' => ['chef', 'cocinero', 'gastronomia', 'repostero', 'alimentos y bebidas', 'jefe de cocina']],
		'agricultura' => ['keyword' => 'agricultura', 'scian' => null, 'job_titles' => ['agronomo', 'agricola', 'campo', 'produccion agricola', 'inocuidad', 'riego']],
	];

	public function buscarPorCarrera(string $carrera, float $lat, float $lon, int $radio = 5000, int $limit = 20): array
	{
		$mapping = $this->careerMapping($carrera);
		$empresas = $this->buscar($mapping['keyword'], $lat, $lon, $radio);

		$items = array_slice(array_map(fn(array $item): array => $this->mapEmpresa($item, $mapping), $empresas), 0, $limit);

		return [
			'carrera' => $carrera,
			'mapping' => $mapping,
			'items' => $items,
		];
	}

	public function buscar(string $condicion, float $lat, float $lon, int $radio = 5000): array
	{
		$token = Env::get('DENUE_TOKEN', '') ?? '';
		if ($token === '') {
			throw new RuntimeException('DENUE_TOKEN no esta configurado.');
		}

		$baseUrl = rtrim(Env::get('DENUE_BASE_URL', 'https://www.inegi.org.mx/app/api/denue/v1/consulta/Buscar') ?? '', '/');
		$url = sprintf(
			'%s/%s/%s,%s/%d/%s',
			$baseUrl,
			rawurlencode($condicion),
			$this->formatCoordinate($lat),
			$this->formatCoordinate($lon),
			min($radio, 5000),
			rawurlencode($token)
		);

		$response = @file_get_contents($url);
		if ($response === false) {
			throw new RuntimeException('No se pudo consultar DENUE.');
		}

		$data = json_decode($response, true);
		if (is_array($data) === false) {
			throw new RuntimeException('DENUE devolvio una respuesta invalida.');
		}

		return $data;
	}

	public function careerMapping(string $carrera): array
	{
		$key = $this->normalize($carrera);
		foreach (self::CAREER_MAP as $needle => $mapping) {
			if (str_contains($key, $needle)) {
				return $mapping;
			}
		}

		return ['keyword' => $carrera, 'scian' => null];
	}

	private function mapEmpresa(array $item, array $mapping): array
	{
		return [
			'id_denue' => $item['Id'] ?? $item['id'] ?? null,
			'nombre' => $item['Nombre'] ?? $item['nombre'] ?? $item['Razon_social'] ?? null,
			'razon_social' => $item['Razon_social'] ?? $item['Nombre'] ?? null,
			'actividad' => $item['Clase_actividad'] ?? null,
			'estrato' => $item['Estrato'] ?? null,
			'tipo_vialidad' => $item['Tipo_vialidad'] ?? null,
			'calle' => $item['Calle'] ?? null,
			'numero_exterior' => $item['Num_Exterior'] ?? null,
			'colonia' => $item['Colonia'] ?? null,
			'codigo_postal' => $item['CP'] ?? null,
			'municipio' => $item['Municipio'] ?? null,
			'estado' => $item['Entidad'] ?? null,
			'telefono' => $item['Telefono'] ?? null,
			'correo' => $item['Correo_e'] ?? null,
			'sitio_web' => $item['Sitio_internet'] ?? null,
			'latitud' => isset($item['Latitud']) ? (float) $item['Latitud'] : null,
			'longitud' => isset($item['Longitud']) ? (float) $item['Longitud'] : null,
			'keyword_usado' => $mapping['keyword'],
			'scian_sugerido' => $mapping['scian'],
		];
	}

	private function normalize(string $value): string
	{
		$value = mb_strtolower($value);
		$transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
		return $transliterated === false ? $value : $transliterated;
	}

	private function formatCoordinate(float $coordinate): string
	{
		return number_format($coordinate, 6, '.', '');
	}
}
