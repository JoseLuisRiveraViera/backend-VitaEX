<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Contratacion;
use Throwable;

class ContratacionController
{
	public function index(): void
	{
		try { Response::success((new Contratacion())->all(), 'Contrataciones encontradas'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron listar las contrataciones'); }
	}

	public function show(string $cve_contratacion): void
	{
		try {
			$row = (new Contratacion())->find($cve_contratacion);
			$row === null ? Response::error('Contratación no encontrada', [], 404) : Response::success($row, 'Contratación encontrada');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo consultar la contratación'); }
	}

	public function empresa(string $cve_empresa): void
	{
		try { Response::success((new Contratacion())->porEmpresa($cve_empresa), 'Contrataciones de empresa'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar las contrataciones'); }
	}

	public function egresado(string $cve_egresado): void
	{
		try { Response::success((new Contratacion())->porEgresado($cve_egresado), 'Contrataciones de egresado'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar las contrataciones'); }
	}

	public function store(): void
	{
		try { Response::success((new Contratacion())->create(Request::body()), 'Contratación creada', 201); }
		catch (Throwable $e) { Response::exception($e, 'No se pudo crear la contratación'); }
	}

	public function confirmarEgresado(string $cve_contratacion): void
	{
		try {
			$row = (new Contratacion())->confirmarEgresado($cve_contratacion);
			$row === null ? Response::error('Contratación no encontrada', [], 404) : Response::success($row, 'Contratación confirmada');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo confirmar la contratación'); }
	}
}
