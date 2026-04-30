<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\models\Egresado;
use app\services\MatchingService;
use Throwable;

class EgresadoController
{
	public function index(): void
	{
		try {
			Response::success((new Egresado())->all(), 'Egresados encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron listar los egresados', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function show(string $cve_egresado): void
	{
		try {
			$row = (new Egresado())->find($cve_egresado);
			$row === null ? Response::error('Egresado no encontrado', [], 404) : Response::success($row, 'Egresado encontrado');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar el egresado', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function perfil(string $cve_egresado): void
	{
		try {
			$row = (new Egresado())->perfil($cve_egresado);
			$row === null ? Response::error('Perfil no encontrado', [], 404) : Response::success($row, 'Perfil encontrado');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar el perfil', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function actualizarPerfil(string $cve_egresado): void
	{
		try {
			$body = Request::body();
			$errors = Validator::notBlankWhenPresent($body, ['url_foto']);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			$row = (new Egresado())->actualizarPerfil($cve_egresado, $body);
			$row === null ? Response::error('Perfil no encontrado', [], 404) : Response::success($row, 'Perfil actualizado');
		} catch (Throwable $exception) {
			Response::error('No se pudo actualizar el perfil', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function postulaciones(string $cve_egresado): void
	{
		try {
			Response::success((new Egresado())->postulaciones($cve_egresado), 'Postulaciones encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar las postulaciones', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function evaluaciones(string $cve_egresado): void
	{
		try {
			Response::success((new Egresado())->evaluaciones($cve_egresado), 'Evaluaciones encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar las evaluaciones', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function matching(string $cve_egresado): void
	{
		try {
			Response::success((new MatchingService())->porEgresado($cve_egresado), 'Matching encontrado');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar el matching', ['detail' => $exception->getMessage()], 500);
		}
	}
}
