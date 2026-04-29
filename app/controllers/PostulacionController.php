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
