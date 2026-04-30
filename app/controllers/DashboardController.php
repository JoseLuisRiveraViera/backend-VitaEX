<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Response;
use app\models\Dashboard;
use Throwable;

class DashboardController
{
	public function insercion(): void
	{
		try {
			Response::success((new Dashboard())->insercion(), 'Inserción laboral por carrera');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar inserción laboral', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function convenios(): void
	{
		try {
			Response::success((new Dashboard())->convenios(), 'Convenios encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar convenios', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function competencias(): void
	{
		try {
			Response::success((new Dashboard())->competencias(), 'Competencias encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar competencias', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function empresa(string $cve_empresa): void
	{
		try {
			Response::success((new Dashboard())->empresa($cve_empresa), 'Dashboard de empresa');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar dashboard de empresa', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function egresado(string $cve_egresado): void
	{
		try {
			Response::success((new Dashboard())->egresado($cve_egresado), 'Dashboard de egresado');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar dashboard de egresado', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function vacantes(): void
	{
		try {
			Response::success((new Dashboard())->vacantes(), 'Dashboard de vacantes');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo consultar dashboard de vacantes');
		}
	}
}
