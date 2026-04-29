<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\SolicitudConvenio;
use Throwable;

class SolicitudConvenioController
{
	public function index(): void
	{
		try {
			Response::success((new SolicitudConvenio())->all(), 'Solicitudes de convenio encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron listar las solicitudes', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function store(): void
	{
		try {
			Response::success((new SolicitudConvenio())->create(Request::body()), 'Solicitud de convenio creada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear la solicitud', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function update(string $cve_solicitud): void
	{
		try {
			$row = (new SolicitudConvenio())->update($cve_solicitud, Request::body());
			$row === null ? Response::error('Solicitud no encontrada', [], 404) : Response::success($row, 'Solicitud de convenio actualizada');
		} catch (Throwable $exception) {
			Response::error('No se pudo actualizar la solicitud', ['detail' => $exception->getMessage()], 422);
		}
	}
}
