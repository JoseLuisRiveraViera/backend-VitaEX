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
			$body = Request::body();
			$errors = $this->validateVacantePayload($body, true);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			Response::success((new Vacante())->create($body), 'Vacante creada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear la vacante', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function update(string $cve_vacante): void
	{
		try {
			$body = Request::body();
			$errors = $this->validateVacantePayload($body, false);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			$row = (new Vacante())->update($cve_vacante, $body);
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
			$errors = array_merge($errors, $this->validatePerfilIdoneo($body, true));
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
			$body = Request::body();
			$errors = $this->validatePerfilIdoneo($body, false);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			Response::success((new Vacante())->actualizarPerfilIdoneo($cve_vacante, $body), 'Perfil idóneo actualizado');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudo actualizar el perfil idóneo');
		}
	}

	private function validateVacantePayload(array $body, bool $creating): array
	{
		$errors = [];
		$required = ['cve_empresa', 'titulo', 'descripcion', 'area', 'modalidad', 'fecha_cierre'];
		if ($creating) {
			$errors = Validator::required($body, $required);
			if (empty($body['cve_ubicacion']) && trim((string) ($body['ubicacion'] ?? '')) === '') {
				$errors['ubicacion'] = 'La ubicación es requerida.';
			}
			if (empty($body['salario_rango']) && empty($body['salario_minimo']) && empty($body['salario_maximo'])) {
				$errors['salario'] = 'El salario es requerido.';
			}
			if (empty($body['perfil_idoneo']) || is_array($body['perfil_idoneo']) === false) {
				$errors['perfil_idoneo'] = 'El perfil idóneo es requerido.';
			}
		}

		foreach (['titulo', 'descripcion', 'area', 'modalidad', 'fecha_cierre'] as $field) {
			if (array_key_exists($field, $body) && trim((string) $body[$field]) === '') {
				$errors[$field] = 'Este campo no puede estar vacío.';
			}
		}

		if (isset($body['perfil_idoneo']) && is_array($body['perfil_idoneo'])) {
			$errors = array_merge($errors, $this->validatePerfilIdoneo($body['perfil_idoneo'], true));
		}

		return $errors;
	}

	private function validatePerfilIdoneo(array $perfil, bool $required): array
	{
		$fields = ['puntaje_psicometrica', 'puntaje_cognitiva', 'puntaje_tecnica', 'puntaje_proyectiva'];
		$errors = [];
		if ($required) {
			$errors = Validator::required($perfil, $fields);
		}

		$total = 0;
		$present = 0;
		foreach ($fields as $field) {
			if (!array_key_exists($field, $perfil)) {
				continue;
			}
			$value = (float) $perfil[$field];
			$present++;
			$total += $value;
			if ($value < 0 || $value > 100) {
				$errors[$field] = 'El valor debe estar entre 0 y 100.';
			}
		}

		if ($present === count($fields) && abs($total - 100) > 0.001) {
			$errors['perfil_idoneo'] = 'Los pesos del perfil idóneo deben sumar 100%.';
		}

		return $errors;
	}
}
