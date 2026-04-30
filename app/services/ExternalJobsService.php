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
		if ($url === '') {
			throw new RuntimeException('EXTERNAL_JOBS_API_URL no esta configurado.');
		}

		$response = @file_get_contents($url);
		if ($response === false) {
			throw new RuntimeException('No se pudo consumir la API externa de vacantes.');
		}

		$decoded = json_decode($response, true);
		$jobs = is_array($decoded) ? ($decoded['data'] ?? $decoded) : [];

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
}
