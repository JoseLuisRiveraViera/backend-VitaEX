<?php
declare(strict_types=1);

use app\controllers\AuthController;
use app\controllers\DashboardController;
use app\controllers\EgresadoController;
use app\controllers\EmpresaController;
use app\controllers\EvaluacionController;
use app\controllers\MensajeController;
use app\controllers\PostulacionController;
use app\controllers\SolicitudConvenioController;
use app\controllers\VacanteController;
use app\core\Response;

Flight::route('GET /api/health', function (): void {
	Response::success(['status' => 'ok'], 'API Bolsa de Trabajo UT funcionando');
});

Flight::route('POST /api/auth/login', [AuthController::class, 'login']);
Flight::route('GET /api/auth/me', [AuthController::class, 'me']);

Flight::route('GET /api/egresados', [EgresadoController::class, 'index']);
Flight::route('GET /api/egresados/@cve_egresado', [EgresadoController::class, 'show']);
Flight::route('GET /api/egresados/@cve_egresado/perfil', [EgresadoController::class, 'perfil']);
Flight::route('PUT /api/egresados/@cve_egresado/perfil', [EgresadoController::class, 'actualizarPerfil']);
Flight::route('GET /api/egresados/@cve_egresado/postulaciones', [EgresadoController::class, 'postulaciones']);
Flight::route('GET /api/egresados/@cve_egresado/evaluaciones', [EgresadoController::class, 'evaluaciones']);
Flight::route('GET /api/egresados/@cve_egresado/matching', [EgresadoController::class, 'matching']);

Flight::route('GET /api/empresas', [EmpresaController::class, 'index']);
Flight::route('GET /api/empresas/@cve_empresa', [EmpresaController::class, 'show']);
Flight::route('POST /api/empresas', [EmpresaController::class, 'store']);
Flight::route('PUT /api/empresas/@cve_empresa', [EmpresaController::class, 'update']);
Flight::route('GET /api/empresas/@cve_empresa/vacantes', [EmpresaController::class, 'vacantes']);
Flight::route('GET /api/empresas/@cve_empresa/candidatos', [EmpresaController::class, 'candidatos']);

Flight::route('POST /api/solicitudes-convenio', [SolicitudConvenioController::class, 'store']);
Flight::route('GET /api/solicitudes-convenio', [SolicitudConvenioController::class, 'index']);
Flight::route('PUT /api/solicitudes-convenio/@cve_solicitud', [SolicitudConvenioController::class, 'update']);

Flight::route('GET /api/vacantes', [VacanteController::class, 'index']);
Flight::route('GET /api/vacantes/@cve_vacante', [VacanteController::class, 'show']);
Flight::route('POST /api/vacantes', [VacanteController::class, 'store']);
Flight::route('PUT /api/vacantes/@cve_vacante', [VacanteController::class, 'update']);
Flight::route('DELETE /api/vacantes/@cve_vacante', [VacanteController::class, 'destroy']);
Flight::route('GET /api/vacantes/@cve_vacante/candidatos', [VacanteController::class, 'candidatos']);
Flight::route('POST /api/vacantes/@cve_vacante/perfil-idoneo', [VacanteController::class, 'perfilIdoneo']);

Flight::route('GET /api/tipos-prueba', [EvaluacionController::class, 'tiposPrueba']);
Flight::route('GET /api/evaluaciones/preguntas/@cve_tipo_prueba', [EvaluacionController::class, 'preguntas']);
Flight::route('POST /api/evaluaciones/iniciar', [EvaluacionController::class, 'iniciar']);
Flight::route('POST /api/evaluaciones/@cve_evaluacion/responder', [EvaluacionController::class, 'responder']);
Flight::route('POST /api/evaluaciones/@cve_evaluacion/finalizar', [EvaluacionController::class, 'finalizar']);

Flight::route('POST /api/postulaciones', [PostulacionController::class, 'store']);
Flight::route('PUT /api/postulaciones/@cve_postulacion/estatus', [PostulacionController::class, 'estatus']);

Flight::route('GET /api/mensajes/egresado/@cve_egresado', [MensajeController::class, 'egresado']);
Flight::route('GET /api/mensajes/empresa/@cve_empresa', [MensajeController::class, 'empresa']);
Flight::route('POST /api/mensajes', [MensajeController::class, 'store']);
Flight::route('PUT /api/mensajes/@cve_mensaje/leido', [MensajeController::class, 'leido']);

Flight::route('GET /api/dashboard/admin/insercion', [DashboardController::class, 'insercion']);
Flight::route('GET /api/dashboard/admin/convenios', [DashboardController::class, 'convenios']);
Flight::route('GET /api/dashboard/admin/competencias', [DashboardController::class, 'competencias']);
Flight::route('GET /api/dashboard/empresa/@cve_empresa', [DashboardController::class, 'empresa']);
Flight::route('GET /api/dashboard/egresado/@cve_egresado', [DashboardController::class, 'egresado']);
