<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\models\Vacante;
use Throwable;

class VacanteController
{
	public function index(): void
	{
		try {
			Response::success((new Vacante())->all(Request::query()), 'Vacantes encontradas');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron listar las vacantes');
		}
	}

	public function show(string $cve_vacante): void
	{
		try {
			$row = (new Vacante())->find($cve_vacante);
			$row === null ? Response::error('Vacante no encontrada', [], 404) : Response::success($row, 'Vacante encontrada');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar la vacante', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function store(): void
	{
		try {
			Response::success((new Vacante())->create(Request::body()), 'Vacante creada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear la vacante', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function update(string $cve_vacante): void
	{
		try {
			$row = (new Vacante())->update($cve_vacante, Request::body());
			$row === null ? Response::error('Vacante no encontrada', [], 404) : Response::success($row, 'Vacante actualizada');
		} catch (Throwable $exception) {
			Response::error('No se pudo actualizar la vacante', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function destroy(string $cve_vacante): void
	{
		try {
			$row = (new Vacante())->softDelete($cve_vacante);
			$row === null ? Response::error('Vacante no encontrada', [], 404) : Response::success($row, 'Vacante desactivada');
		} catch (Throwable $exception) {
			Response::error('No se pudo desactivar la vacante', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function candidatos(string $cve_vacante): void
	{
		try {
			$items = (new Vacante())->candidatos($cve_vacante);
			$min = Request::query()['porcentaje_minimo'] ?? null;
			if ($min !== null && is_numeric($min)) {
				$items = array_values(array_filter($items, static fn(array $item): bool => (float) ($item['porcentaje_coincidencia'] ?? 0) >= (float) $min));
			}
			Response::success($items, 'Candidatos encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar los candidatos', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function perfilIdoneo(string $cve_vacante): void
	{
		try {
			$body = Request::body();
			$errors = Validator::required($body, ['puntaje_psicometrica', 'puntaje_cognitiva', 'puntaje_tecnica', 'puntaje_proyectiva']);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			Response::success((new Vacante())->crearPerfilIdoneo($cve_vacante, $body), 'Perfil idóneo creado', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear el perfil idóneo', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function actualizarPerfilIdoneo(string $cve_vacante): void
	{
		try {
			Response::success((new Vacante())->actualizarPerfilIdoneo($cve_vacante, Request::body()), 'Perfil idóneo actualizado');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo actualizar el perfil idóneo');
		}
	}
}
