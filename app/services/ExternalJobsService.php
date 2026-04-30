<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use app\models\VacanteNacional;
use RuntimeException;

class ExternalJobsService
{
	public function sync(): array
	{
		$url = Env::get('EXTERNAL_JOBS_API_URL', '') ?? '';
		$env = Env::get('APP_ENV', 'local') ?? 'local';

		if ($url === '') {
			if ($env !== 'local') {
				throw new RuntimeException('EXTERNAL_JOBS_API_URL no está configurado.');
			}
			$jobs = $this->mockJobs();
			$url = 'local://mock';
		} else {
			$response = @file_get_contents($url);
			if ($response === false) {
				throw new RuntimeException('No se pudo consumir la API externa de vacantes.');
			}
			$decoded = json_decode($response, true);
			$jobs = is_array($decoded) ? ($decoded['data'] ?? $decoded) : [];
		}

		$model = new VacanteNacional();
		$fuente = $model->fuente('API externa de vacantes', $url);
		$items = [];

		foreach ($jobs as $job) {
			if (is_array($job) === false) {
				continue;
			}
			$mapped = $this->map($job, (int) $fuente['cve_fuente_api']);
			$items[] = $model->upsert($mapped);
		}

		return [
			'total_sincronizadas' => count($items),
			'items' => $items,
		];
	}

	private function map(array $job, int $fuente): array
	{
		return [
			'cve_fuente_api' => $fuente,
			'id_externo' => (string) ($job['id_externo'] ?? $job['id'] ?? md5(json_encode($job))),
			'titulo' => (string) ($job['titulo'] ?? $job['title'] ?? 'Vacante externa'),
			'descripcion' => (string) ($job['descripcion'] ?? $job['description'] ?? ''),
			'empresa_externa' => $job['empresa_nombre'] ?? $job['empresa'] ?? $job['company'] ?? null,
			'ubicacion_texto' => trim((string) (($job['municipio'] ?? '') . ' ' . ($job['estado'] ?? ''))) ?: ($job['ubicacion'] ?? null),
			'modalidad' => $job['modalidad'] ?? null,
			'salario_minimo' => $job['salario_minimo'] ?? null,
			'salario_maximo' => $job['salario_maximo'] ?? null,
			'url_original' => $job['url_original'] ?? $job['url'] ?? null,
			'fecha_publicacion' => $job['fecha_publicacion'] ?? null,
			'datos_originales' => json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		];
	}

	private function mockJobs(): array
	{
		return [
			['id_externo' => 'mock-1', 'titulo' => 'Soporte TI remoto', 'empresa_nombre' => 'Nacional Tech', 'descripcion' => 'Soporte técnico de primer nivel.', 'modalidad' => 'remoto', 'url_original' => 'https://example.com/jobs/mock-1'],
			['id_externo' => 'mock-2', 'titulo' => 'Analista de datos junior', 'empresa_nombre' => 'Data MX', 'descripcion' => 'Reportes y tableros operativos.', 'modalidad' => 'hibrido', 'url_original' => 'https://example.com/jobs/mock-2'],
			['id_externo' => 'mock-3', 'titulo' => 'Desarrollador PHP', 'empresa_nombre' => 'Cloud Nacional', 'descripcion' => 'APIs con PHP y PostgreSQL.', 'modalidad' => 'remoto', 'url_original' => 'https://example.com/jobs/mock-3'],
		];
	}
}
