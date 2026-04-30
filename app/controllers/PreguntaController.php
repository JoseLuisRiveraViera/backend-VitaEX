<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Pregunta;
use Throwable;

class PreguntaController
{
	public function index(): void
	{
		try { Response::success((new Pregunta())->all(), 'Preguntas encontradas'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar preguntas'); }
	}

	public function show(string $cve_pregunta): void
	{
		try {
			$row = (new Pregunta())->find($cve_pregunta);
			$row === null ? Response::error('Pregunta no encontrada', [], 404) : Response::success($row, 'Pregunta encontrada');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo consultar la pregunta'); }
	}

	public function store(): void
	{
		try { Response::success((new Pregunta())->create(Request::body()), 'Pregunta creada', 201); }
		catch (Throwable $e) { Response::exception($e, 'No se pudo crear la pregunta'); }
	}

	public function update(string $cve_pregunta): void
	{
		try {
			$row = (new Pregunta())->update($cve_pregunta, Request::body());
			$row === null ? Response::error('Pregunta no encontrada', [], 404) : Response::success($row, 'Pregunta actualizada');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo actualizar la pregunta'); }
	}

	public function destroy(string $cve_pregunta): void
	{
		try {
			$row = (new Pregunta())->softDelete($cve_pregunta);
			$row === null ? Response::error('Pregunta no encontrada', [], 404) : Response::success($row, 'Pregunta desactivada');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo desactivar la pregunta'); }
	}

	public function tecnicasEmpresa(string $cve_empresa): void
	{
		try { Response::success((new Pregunta())->tecnicasEmpresa($cve_empresa), 'Preguntas técnicas encontradas'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar preguntas técnicas'); }
	}

	public function storeTecnicaEmpresa(string $cve_empresa): void
	{
		try {
			$body = Request::body();
			$body['cve_empresa'] = $cve_empresa;
			Response::success((new Pregunta())->create($body), 'Pregunta técnica creada', 201);
		} catch (Throwable $e) { Response::exception($e, 'No se pudo crear la pregunta técnica'); }
	}
}
