<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use app\models\VacanteNacional;
use RuntimeException;
use Throwable;

class ExternalJobsService
{
	public function sync(): array
	{
		$url      = Env::get('EXTERNAL_JOBS_API_URL', '') ?? '';
		$theirKey = Env::get('THEIRSTACK_API_KEY', '') ?? '';
		$env      = Env::get('APP_ENV', 'local') ?? 'local';

		if ($url !== '') {
			// URL de bolsa externa (Adzuna, Jooble, etc.)
			$response = @file_get_contents($url);
			if ($response === false) {
				throw new RuntimeException('No se pudo consumir la API externa de vacantes.');
			}
			$decoded = json_decode($response, true);
			// Adzuna usa 'results', Remotive 'jobs', otras 'data' o array directo
			$jobs   = is_array($decoded) ? ($decoded['results'] ?? $decoded['data'] ?? $decoded['jobs'] ?? $decoded) : [];
			$source = $url;
		} elseif ($theirKey !== '') {
			// TheirStack como fuente de vacantes nacionales para México
			$jobs   = $this->syncFromTheirStack();
			$source = Env::get('THEIRSTACK_BASE_URL', 'https://api.theirstack.com/v1/jobs/search') ?? '';
		} else {
			if ($env !== 'local') {
				throw new RuntimeException('Configura EXTERNAL_JOBS_API_URL o THEIRSTACK_API_KEY.');
			}
			$jobs   = $this->mockJobs();
			$source = 'local://mock';
		}

		$model  = new VacanteNacional();
		$fuente = $model->fuente('API externa de vacantes', $source);
		$items  = [];

		foreach ($jobs as $job) {
			if (is_array($job) === false) {
				continue;
			}
			$mapped  = $this->map($job, (int) $fuente['cve_fuente_api']);
			$items[] = $model->upsert($mapped);
		}

		return [
			'total_sincronizadas' => count($items),
			'items'               => $items,
		];
	}

	/**
	 * Busca vacantes en México via TheirStack, agrupando por categorías
	 * relevantes para egresados de una universidad tecnológica.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function syncFromTheirStack(): array
	{
		$theirStack = new TheirStackService();
		$country    = Env::get('THEIRSTACK_COUNTRY_CODE', 'MX') ?? 'MX';
		$maxDays    = (int) (Env::get('THEIRSTACK_POSTED_MAX_AGE_DAYS', '60') ?? 60);

		// 3 grupos → 3 llamadas a la API; hasta 20 vacantes por grupo = 60 vacantes
		$groups = [
			// TI / Software / Sistemas
			['desarrollador', 'programador', 'software engineer', 'frontend developer',
			 'backend developer', 'full stack', 'devops', 'soporte tecnico TI',
			 'analista sistemas', 'qa tester', 'data analyst'],

			// Ingeniería / Industrial / Manufactura
			['ingeniero industrial', 'tecnico mantenimiento', 'control de calidad',
			 'supervisor produccion', 'logistica', 'almacen', 'mecatronico',
			 'seguridad industrial', 'manufactura', 'operador maquinaria'],

			// Negocios / Administración / Servicios
			['administrador', 'contador', 'recursos humanos', 'reclutador',
			 'analista financiero', 'ejecutivo ventas', 'atencion al cliente',
			 'marketing digital', 'community manager', 'coordinador administrativo'],
		];

		$allJobs = [];
		foreach ($groups as $titles) {
			try {
				$results = $theirStack->search([
					'job_title_or'           => $titles,
					'job_country_code_or'    => [$country],
					'posted_at_max_age_days' => $maxDays,
					'limit'                  => 20,
					'page'                   => 0,
				]);
				foreach ($results as $job) {
					$allJobs[] = $this->normalizeTheirStack($job);
				}
			} catch (Throwable) {
				// Si un grupo falla (rate limit, etc.), continuamos con los demás
			}
		}

		return $allJobs;
	}

	/**
	 * Adapta los campos de TheirStackService::mapJob() al formato
	 * que espera ExternalJobsService::map().
	 *
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>
	 */
	private function normalizeTheirStack(array $job): array
	{
		return [
			'id'               => $job['id'] ?? null,
			'titulo'           => $job['titulo'] ?? null,
			'empresa_nombre'   => $job['empresa'] ?? null,
			'descripcion'      => $job['descripcion'] ?? null,
			'url_original'     => $job['url'] ?? null,
			'modalidad'        => ($job['remoto'] ?? false) ? 'remoto' : null,
			'ubicacion'        => $job['ubicacion'] ?? null,
			'fecha_publicacion' => $job['fecha_publicacion'] ?? null,
			'salario_minimo'   => null,
			'salario_maximo'   => null,
		];
	}

	/**
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>
	 */
	private function map(array $job, int $fuente): array
	{
		// Adzuna: company.display_name | location.display_name | redirect_url | salary_min/max | created
		// Remotive: company_name | candidate_required_location | url | salary | publication_date
		// TheirStack (normalizado): empresa_nombre | ubicacion | url_original | modalidad
		// Genérico: empresa_nombre | ubicacion | url_original | salario_minimo | fecha_publicacion
		$empresa = $job['empresa_nombre']
			?? $job['empresa']
			?? $job['company_name']
			?? (isset($job['company']) && is_array($job['company'])
				? ($job['company']['display_name'] ?? null)
				: ($job['company'] ?? null));

		$ubicacion = trim((string) (($job['municipio'] ?? '') . ' ' . ($job['estado'] ?? '')))
			?: ($job['ubicacion_texto']
			?? $job['ubicacion']
			?? $job['candidate_required_location']
			?? (isset($job['location']) && is_array($job['location'])
				? ($job['location']['display_name'] ?? null)
				: null));

		$url      = $job['url_original'] ?? $job['url'] ?? $job['redirect_url'] ?? null;
		$fechaPub = $job['fecha_publicacion'] ?? $job['publication_date'] ?? $job['created'] ?? null;

		return [
			'cve_fuente_api'    => $fuente,
			'id_externo'        => (string) ($job['id_externo'] ?? $job['id'] ?? md5(json_encode($job))),
			'titulo'            => (string) ($job['titulo'] ?? $job['title'] ?? 'Vacante externa'),
			'descripcion'       => (string) ($job['descripcion'] ?? $job['description'] ?? ''),
			'empresa_externa'   => $empresa,
			'ubicacion_texto'   => $ubicacion,
			'modalidad'         => $job['modalidad'] ?? null,
			'salario_minimo'    => $job['salario_minimo'] ?? $job['salary_min'] ?? null,
			'salario_maximo'    => $job['salario_maximo'] ?? $job['salary_max'] ?? null,
			'url_original'      => $url,
			'fecha_publicacion' => $fechaPub ? substr((string) $fechaPub, 0, 10) : null,
			'datos_originales'  => json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		];
	}

	/** @return array<int, array<string, mixed>> */
	private function mockJobs(): array
	{
		return [
			['id_externo' => 'mock-1', 'titulo' => 'Soporte TI remoto', 'empresa_nombre' => 'Nacional Tech', 'descripcion' => 'Soporte técnico de primer nivel.', 'modalidad' => 'remoto', 'url_original' => 'https://example.com/jobs/mock-1'],
			['id_externo' => 'mock-2', 'titulo' => 'Analista de datos junior', 'empresa_nombre' => 'Data MX', 'descripcion' => 'Reportes y tableros operativos.', 'modalidad' => 'hibrido', 'url_original' => 'https://example.com/jobs/mock-2'],
			['id_externo' => 'mock-3', 'titulo' => 'Desarrollador PHP', 'empresa_nombre' => 'Cloud Nacional', 'descripcion' => 'APIs con PHP y PostgreSQL.', 'modalidad' => 'remoto', 'url_original' => 'https://example.com/jobs/mock-3'],
		];
	}
}
