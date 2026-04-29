<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Evaluacion;
use Throwable;

class EvaluacionController
{
	public function tiposPrueba(): void
	{
		try {
			Response::success((new Evaluacion())->tiposPrueba(), 'Tipos de prueba encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar los tipos de prueba', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function preguntas(string $cve_tipo_prueba): void
	{
		try {
			Response::success((new Evaluacion())->preguntas($cve_tipo_prueba), 'Preguntas encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar las preguntas', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function iniciar(): void
	{
		try {
			Response::success((new Evaluacion())->iniciar(Request::body()), 'Evaluación iniciada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo iniciar la evaluación', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function responder(string $cve_evaluacion): void
	{
		try {
			Response::success((new Evaluacion())->responder($cve_evaluacion, Request::body()), 'Respuesta registrada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo registrar la respuesta', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function finalizar(string $cve_evaluacion): void
	{
		try {
			$row = (new Evaluacion())->finalizar($cve_evaluacion, Request::body());
			$row === null ? Response::error('Evaluación no encontrada', [], 404) : Response::success($row, 'Evaluación finalizada');
		} catch (Throwable $exception) {
			Response::error('No se pudo finalizar la evaluación', ['detail' => $exception->getMessage()], 422);
		}
	}
}
