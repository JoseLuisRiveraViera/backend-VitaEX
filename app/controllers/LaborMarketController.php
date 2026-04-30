<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\services\DenueService;
use app\services\LaborMarketService;
use app\services\TheirStackService;
use Throwable;

class LaborMarketController
{
	public function empresasVacantes(): void
	{
		try {
			Response::success((new LaborMarketService())->empresasConVacantes(Request::query()), 'Empresas DENUE con vacantes activas en México');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo consultar mercado laboral');
		}
	}

	public function denueEmpresas(): void
	{
		try {
			$query = Request::query();
			$carrera = (string) ($query['carrera'] ?? 'Tecnologías de la Información');
			$lat = (float) ($query['lat'] ?? 21.8120);
			$lon = (float) ($query['lon'] ?? -105.2080);
			$radio = (int) ($query['radio'] ?? 5000);
			Response::success((new DenueService())->buscarPorCarrera($carrera, $lat, $lon, $radio), 'Empresas DENUE encontradas');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo consultar DENUE');
		}
	}

	public function theirStackVacantes(): void
	{
		try {
			$query = Request::query();
			$empresa = (string) ($query['empresa'] ?? '');
			if ($empresa === '') {
				Response::error('Empresa requerida', ['empresa' => 'Envía ?empresa=Nombre'], 422);
				return;
			}
			$titulos = [];
			if (!empty($query['titulos'])) {
				$titulos = array_values(array_filter(array_map('trim', explode(',', (string) $query['titulos']))));
			}
			Response::success((new TheirStackService())->jobsByCompanyName($empresa, 5, $titulos), 'Vacantes activas en México encontradas');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo consultar TheirStack');
		}
	}
}
