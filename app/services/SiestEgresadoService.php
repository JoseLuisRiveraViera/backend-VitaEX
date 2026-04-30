<?php
declare(strict_types=1);

namespace app\services;

use app\config\Env;
use RuntimeException;

class SiestEgresadoService
{
	public function find(string $cvePersona): array
	{
		$url = rtrim(Env::get('SIEST_EGRESADO_URL', '') ?? '', '/');
		if ($url === '') {
			throw new RuntimeException('SIEST_EGRESADO_URL no está configurado.');
		}

		$response = @file_get_contents($url . '/' . rawurlencode($cvePersona));
		if ($response === false) {
			throw new RuntimeException('No se pudo consultar egresado en SIEst.');
		}

		$data = json_decode($response, true);
		if (is_array($data) === false) {
			throw new RuntimeException('Respuesta inválida de SIEst egresados.');
		}

		return $data;
	}
}
