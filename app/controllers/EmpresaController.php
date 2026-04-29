<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Empresa;
use Throwable;

class EmpresaController
{
	public function index(): void
	{
		try {
			Response::success((new Empresa())->all(), 'Empresas encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron listar las empresas', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function show(string $cve_empresa): void
	{
		try {
			$row = (new Empresa())->find($cve_empresa);
			$row === null ? Response::error('Empresa no encontrada', [], 404) : Response::success($row, 'Empresa encontrada');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar la empresa', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function store(): void
	{
		try {
			Response::success((new Empresa())->create(Request::body()), 'Empresa creada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear la empresa', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function update(string $cve_empresa): void
	{
		try {
			$row = (new Empresa())->update($cve_empresa, Request::body());
			$row === null ? Response::error('Empresa no encontrada', [], 404) : Response::success($row, 'Empresa actualizada');
		} catch (Throwable $exception) {
			Response::error('No se pudo actualizar la empresa', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function vacantes(string $cve_empresa): void
	{
		try {
			Response::success((new Empresa())->vacantes($cve_empresa), 'Vacantes de empresa encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar las vacantes de la empresa', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function candidatos(string $cve_empresa): void
	{
		try {
			Response::success((new Empresa())->candidatos($cve_empresa), 'Candidatos encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar los candidatos', ['detail' => $exception->getMessage()], 500);
		}
	}
}
