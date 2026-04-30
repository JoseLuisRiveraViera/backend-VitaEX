<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class DenueService
{
	private const CAREER_MAP = [
		// TI / Sistemas / Software
		'tecnolog'     => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'informac'     => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'sistemas'     => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'software'     => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['developer', 'programador', 'desarrollador', 'javascript', 'python', 'php', 'frontend', 'backend', 'full stack', 'software engineer']],
		'computo'      => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['soporte tecnico', 'tecnico en sistemas', 'helpdesk', 'redes', 'infraestructura']],
		'redes'        => ['keyword' => 'computo', 'scian' => '541510', 'job_titles' => ['administrador de redes', 'network engineer', 'cisco', 'infraestructura', 'telecomunicaciones']],
		// Ingeniería
		'industrial'   => ['keyword' => 'manufactura', 'scian' => '31-33', 'job_titles' => ['ingeniero industrial', 'calidad', 'procesos', 'produccion', 'manufactura', 'mejora continua', 'seguridad industrial']],
		'mecatron'     => ['keyword' => 'manufactura', 'scian' => '31-33', 'job_titles' => ['mecatronico', 'automatizacion', 'robotica', 'plc', 'mantenimiento industrial']],
		'mantenimient' => ['keyword' => 'mantenimiento', 'scian' => '811', 'job_titles' => ['tecnico de mantenimiento', 'mantenimiento preventivo', 'electricista industrial', 'mecanico industrial']],
		'electronica'  => ['keyword' => 'electronica', 'scian' => '334', 'job_titles' => ['tecnico electronico', 'electronico', 'instrumentacion', 'control']],
		'logistic'     => ['keyword' => 'transporte', 'scian' => '484', 'job_titles' => ['logistica', 'almacen', 'inventario', 'cadena de suministro', 'operador de almacen']],
		// Negocios
		'administrac'  => ['keyword' => 'administracion', 'scian' => null, 'job_titles' => ['administrador', 'administrativo', 'auxiliar administrativo', 'coordinador administrativo', 'gerente administrativo']],
		'contadur'     => ['keyword' => 'contabilidad', 'scian' => '541211', 'job_titles' => ['contador', 'contable', 'auxiliar contable', 'auditor', 'impuestos', 'nominas']],

		'contabilidad' => ['keyword' => 'contabilidad', 'scian' => '541211', 'job_titles' => ['contador', 'contable', 'auxiliar contable', 'auditor', 'impuestos', 'nominas']],
		'finanzas'     => ['keyword' => 'finanzas', 'scian' => '522', 'job_titles' => ['analista financiero', 'finanzas', 'tesorero', 'cuentas por cobrar', 'cuentas por pagar']],
		'marketing'    => ['keyword' => 'publicidad', 'scian' => '541810', 'job_titles' => ['marketing', 'mercadotecnia', 'community manager', 'publicidad', 'ventas digitales']],
		'ventas'       => ['keyword' => 'ventas', 'scian' => null, 'job_titles' => ['vendedor', 'asesor comercial', 'ejecutivo de ventas', 'representante comercial']],
		// Salud y Social
		'psicolog'     => ['keyword' => 'psicologia', 'scian' => '621330', 'job_titles' => ['psicologo', 'recursos humanos', 'reclutador', 'talento humano', 'orientador']],
		'enfermeria'   => ['keyword' => 'hospital', 'scian' => '621', 'job_titles' => ['enfermera', 'auxiliar de enfermeria', 'tecnico en enfermeria', 'salud']],
		'nutricion'    => ['keyword' => 'hospital', 'scian' => '621', 'job_titles' => ['nutriologo', 'nutricionista', 'dietetica', 'salud']],
		// Diseño y Arte
		'diseño'       => ['keyword' => 'diseño', 'scian' => '541430', 'job_titles' => ['diseñador grafico', 'diseñador industrial', 'ilustrador', 'ui ux', 'creatividad']],
		'arquitectura' => ['keyword' => 'arquitectura', 'scian' => '541310', 'job_titles' => ['arquitecto', 'dibujante', 'proyectista', 'revit', 'autocad', 'bim']],
		// Servicios
		'turismo'      => ['keyword' => 'hotel', 'scian' => null, 'job_titles' => ['recepcionista', 'reservaciones', 'guest service', 'agente de viajes', 'guia turistico']],
		'gastronom'    => ['keyword' => 'restaurante', 'scian' => null, 'job_titles' => ['chef', 'cocinero', 'repostero', 'alimentos y bebidas', 'jefe de cocina']],
		'alimentos'    => ['keyword' => 'restaurante', 'scian' => null, 'job_titles' => ['chef', 'cocinero', 'nutricion', 'control de calidad alimentos']],
		// Primario
		'agricultur'   => ['keyword' => 'agricultura', 'scian' => null, 'job_titles' => ['agronomo', 'campo', 'produccion agricola', 'inocuidad', 'riego']],
		'agronomo'     => ['keyword' => 'agricultura', 'scian' => null, 'job_titles' => ['agronomo', 'campo', 'produccion agricola', 'inocuidad', 'riego']],
		'pesca'        => ['keyword' => 'pesca', 'scian' => null, 'job_titles' => ['acuicultura', 'pesca', 'produccion pesquera']],
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
