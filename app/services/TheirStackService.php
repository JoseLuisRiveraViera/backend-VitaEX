<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class TheirStackService
{
	public function jobsByCompanyName(string $companyName, int $limit = 5, array $jobTitles = []): array
	{
		$key = Env::get('THEIRSTACK_API_KEY', '') ?? '';
		if ($key === '') {
			if ((Env::get('APP_ENV', 'local') ?? 'local') === 'local') {
				return $this->mockJobs($companyName);
			}
			throw new RuntimeException('THEIRSTACK_API_KEY no está configurado.');
		}

		$body = [
			'company_name_or' => [$companyName],
			'job_country_code_or' => [Env::get('THEIRSTACK_COUNTRY_CODE', 'MX') ?? 'MX'],
			'posted_at_max_age_days' => (int) (Env::get('THEIRSTACK_POSTED_MAX_AGE_DAYS', '60') ?? 60),
			'limit' => $limit,
			'page' => 0,
		];

		if ($jobTitles !== []) {
			$body['job_title_or'] = array_values($jobTitles);
		}

		return $this->search($body);
	}

	public function search(array $body): array
	{
		$key = Env::get('THEIRSTACK_API_KEY', '') ?? '';
		if ($key === '') {
			throw new RuntimeException('THEIRSTACK_API_KEY no está configurado.');
		}

		$url = Env::get('THEIRSTACK_BASE_URL', 'https://api.theirstack.com/v1/jobs/search') ?? 'https://api.theirstack.com/v1/jobs/search';
		$ch = curl_init($url);
		if ($ch === false) {
			throw new RuntimeException('No se pudo iniciar conexión con TheirStack.');
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $key,
				'Content-Type: application/json',
			],
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			CURLOPT_TIMEOUT => 20,
		]);

		$response = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);

		if ($response === false || $status >= 400) {
			throw new RuntimeException('TheirStack rechazó la consulta. HTTP ' . $status . ' ' . $error);
		}

		$data = json_decode((string) $response, true);
		if (is_array($data) === false) {
			throw new RuntimeException('TheirStack devolvió una respuesta inválida.');
		}

		$items = $data['data'] ?? $data['results'] ?? $data['jobs'] ?? [];
		return array_map(fn(array $job): array => $this->mapJob($job), is_array($items) ? $items : []);
	}

	private function mapJob(array $job): array
	{
		return [
			'id' => $job['id'] ?? null,
			'titulo' => $job['job_title'] ?? $job['title'] ?? null,
			'empresa' => $job['company'] ?? $job['company_name'] ?? ($job['company_object']['name'] ?? null),
			'url' => $job['url'] ?? $job['final_url'] ?? null,
			'fecha_publicacion' => $job['date_posted'] ?? null,
			'ubicacion' => $job['location'] ?? null,
			'pais' => $job['country'] ?? null,
			'codigo_pais' => $job['country_code'] ?? null,
			'remoto' => $job['remote'] ?? null,
			'salario' => $job['salary_string'] ?? null,
			'descripcion' => $job['description'] ?? $job['description_text'] ?? null,
		];
	}

	private function mockJobs(string $companyName): array
	{
		return [
			[
				'id' => 'mock-theirstack-1',
				'titulo' => 'Desarrollador PHP Junior',
				'empresa' => $companyName,
				'url' => 'https://example.com/jobs/php-junior',
				'fecha_publicacion' => date('Y-m-d'),
				'ubicacion' => 'Nayarit, México',
				'pais' => 'Mexico',
				'codigo_pais' => 'MX',
				'remoto' => false,
				'salario' => null,
				'descripcion' => 'Vacante mock local TheirStack.',
			],
		];
	}
}
