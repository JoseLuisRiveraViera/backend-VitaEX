<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Mensaje;
use Throwable;

class MensajeController
{
	public function egresado(string $cve_egresado): void
	{
		try {
			Response::success((new Mensaje())->porEgresado($cve_egresado), 'Mensajes encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar los mensajes', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function empresa(string $cve_empresa): void
	{
		try {
			Response::success((new Mensaje())->porEmpresa($cve_empresa), 'Mensajes encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar los mensajes', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function vacante(string $cve_vacante): void
	{
		try {
			Response::success((new Mensaje())->porVacante($cve_vacante), 'Mensajes encontrados');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron consultar los mensajes');
		}
	}

	public function store(): void
	{
		try {
			Response::success((new Mensaje())->create(Request::body()), 'Mensaje creado', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear el mensaje', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function leido(string $cve_mensaje): void
	{
		try {
			$row = (new Mensaje())->marcarLeido($cve_mensaje);
			$row === null ? Response::error('Mensaje no encontrado', [], 404) : Response::success($row, 'Mensaje marcado como leído');
		} catch (Throwable $exception) {
			Response::error('No se pudo marcar el mensaje como leído', ['detail' => $exception->getMessage()], 422);
		}
	}
}
