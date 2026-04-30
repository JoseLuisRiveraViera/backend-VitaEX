<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use Throwable;

class LaborMarketService
{
	public function empresasConVacantes(array $query): array
	{
		$carrera = (string) ($query['carrera'] ?? 'Tecnologías de la Información');
		$lat = (float) ($query['lat'] ?? Env::get('DENUE_DEFAULT_LAT', '21.8120'));
		$lon = (float) ($query['lon'] ?? Env::get('DENUE_DEFAULT_LON', '-105.2080'));
		$radio = (int) ($query['radio'] ?? Env::get('DENUE_DEFAULT_RADIUS', '5000'));
		$limit = max(1, min(25, (int) ($query['limit'] ?? 10)));
		$jobsLimit = max(1, min(10, (int) ($query['jobs_limit'] ?? 5)));

		$denue = new DenueService();
		$theirStack = new TheirStackService();
		$result = $denue->buscarPorCarrera($carrera, $lat, $lon, min($radio, 5000), $limit);
		$jobTitles = $this->resolveJobTitles($query, $result['mapping']['job_titles'] ?? []);

		$items = [];
		foreach ($result['items'] as $empresa) {
			$jobs = [];
			$error = null;
			try {
				$jobs = $theirStack->jobsByCompanyName((string) ($empresa['nombre'] ?? $empresa['razon_social'] ?? ''), $jobsLimit, $jobTitles);
			} catch (Throwable $exception) {
				$error = $exception->getMessage();
			}

			$empresa['vacantes_activas_mx'] = $jobs;
			$empresa['total_vacantes_activas_mx'] = count($jobs);
			$empresa['verificacion_theirstack_error'] = $error;
			$items[] = $empresa;
		}

		usort($items, static fn(array $a, array $b): int => $b['total_vacantes_activas_mx'] <=> $a['total_vacantes_activas_mx']);

		return [
			'pais' => 'MX',
			'carrera' => $carrera,
			'mapping' => $result['mapping'],
			'titulos_puesto_usados' => $jobTitles,
			'ubicacion' => [
				'lat' => $lat,
				'lon' => $lon,
				'radio_metros' => min($radio, 5000),
			],
			'items' => $items,
		];
	}

	private function resolveJobTitles(array $query, array $defaults): array
	{
		if (!empty($query['titulos'])) {
			return array_values(array_filter(array_map('trim', explode(',', (string) $query['titulos']))));
		}

		if (!empty($query['job_titles'])) {
			return array_values(array_filter(array_map('trim', explode(',', (string) $query['job_titles']))));
		}

		return array_values(array_filter($defaults, static fn($value): bool => is_string($value) && trim($value) !== ''));
	}
}
