<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\DenueEmpresa;
use app\services\DenueService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DenueController
{
	public function carreras(): void
	{
		Response::success((new DenueService())->careerMappings(), 'Mapeo DENUE por carrera');
	}

	public function buscar(): void
	{
		try {
			Response::success((new DenueService())->buscar(Request::query()), 'Empresas DENUE encontradas');
		} catch (InvalidArgumentException $exception) {
			Response::error('Datos invalidos para consultar DENUE', ['detail' => $exception->getMessage()], 422);
		} catch (RuntimeException $exception) {
			Response::error('No se pudo consultar DENUE', ['detail' => $exception->getMessage()], 503);
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar DENUE', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function importar(): void
	{
		try {
			$body = Request::body();
			$query = Request::query();
			$empresas = $body['empresas'] ?? null;

			if (is_array($empresas) === false) {
				$resultadoBusqueda = (new DenueService())->buscar(array_merge($query, $body));
				$empresas = $resultadoBusqueda['empresas'] ?? [];
			}

			if (is_array($empresas) === false || $empresas === []) {
				Response::error('No hay empresas DENUE para importar', [], 422);
				return;
			}

			$zona = $body['zona'] ?? $query['zona'] ?? null;
			Response::success((new DenueEmpresa())->importar(array_values($empresas), is_string($zona) ? $zona : null), 'Empresas DENUE importadas', 201);
		} catch (InvalidArgumentException $exception) {
			Response::error('Datos invalidos para importar DENUE', ['detail' => $exception->getMessage()], 422);
		} catch (RuntimeException $exception) {
			Response::error('No se pudo importar DENUE', ['detail' => $exception->getMessage()], 503);
		} catch (Throwable $exception) {
			Response::error('No se pudo importar DENUE', ['detail' => $exception->getMessage()], 500);
		}
	}
}
