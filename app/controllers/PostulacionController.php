<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\models\Postulacion;
use Throwable;

class PostulacionController
{
	public function index(): void
	{
		try {
			Response::success((new Postulacion())->all(Request::query()), 'Postulaciones encontradas');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron consultar postulaciones');
		}
	}

	public function show(string $cve_postulacion): void
	{
		try {
			$row = (new Postulacion())->find($cve_postulacion);
			$row === null ? Response::error('Postulación no encontrada', [], 404) : Response::success($row, 'Postulación encontrada');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo consultar la postulación');
		}
	}

	public function store(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['cve_egresado', 'cve_vacante']);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			Response::success((new Postulacion())->create($body['cve_egresado'], $body['cve_vacante']), 'Postulación creada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear la postulación', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function porVacante(string $cve_vacante): void
	{
		try {
			Response::success((new Postulacion())->porVacante($cve_vacante), 'Postulaciones de vacante');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron consultar postulaciones de la vacante');
		}
	}

	public function estatus(string $cve_postulacion): void
	{
		try {
			$row = (new Postulacion())->actualizarEstatus($cve_postulacion, Request::body());
			$row === null ? Response::error('Postulación no encontrada', [], 404) : Response::success($row, 'Estatus actualizado');
		} catch (Throwable $exception) {
			Response::error('No se pudo actualizar el estatus', ['detail' => $exception->getMessage()], 422);
		}
	}
}
