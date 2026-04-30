<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\TrayectoriaAcademica;
use Throwable;

class TrayectoriaController
{
	public function egresado(string $cve_egresado): void
	{
		try {
			Response::success((new TrayectoriaAcademica())->porEgresado($cve_egresado), 'Trayectoria encontrada');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo consultar la trayectoria'); }
	}

	public function store(string $cve_egresado): void
	{
		try {
			$row = (new TrayectoriaAcademica())->create($cve_egresado, Request::body());
			Response::success($row, 'Trayectoria creada', 201);
		} catch (Throwable $e) { Response::exception($e, 'No se pudo crear el registro de trayectoria'); }
	}

	public function update(string $cve_trayectoria): void
	{
		try {
			$row = (new TrayectoriaAcademica())->update($cve_trayectoria, Request::body());
			$row === null
				? Response::error('Registro no encontrado', [], 404)
				: Response::success($row, 'Registro actualizado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo actualizar el registro'); }
	}

	public function destroy(string $cve_trayectoria): void
	{
		try {
			$deleted = (new TrayectoriaAcademica())->delete($cve_trayectoria);
			$deleted === 0
				? Response::error('Registro no encontrado', [], 404)
				: Response::success(['deleted' => true], 'Registro eliminado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo eliminar el registro'); }
	}
}
