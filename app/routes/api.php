<?php
declare(strict_types=1);

use app\controllers\AuthController;
use app\controllers\CertificadoController;
use app\controllers\ContratacionController;
use app\controllers\DashboardController;
use app\controllers\EgresadoController;
use app\controllers\EmpresaController;
use app\controllers\EvaluacionController;
use app\controllers\LaborMarketController;
use app\controllers\MensajeController;
use app\controllers\PostulacionController;
use app\controllers\PreguntaController;
use app\controllers\ReporteController;
use app\controllers\SolicitudConvenioController;
use app\controllers\VacanteNacionalController;
use app\controllers\VacanteController;
use app\core\Response;

Flight::route('GET /api/health', function (): void {
	Response::success(['status' => 'ok'], 'API Bolsa de Trabajo UT funcionando');
});

Flight::route('POST /api/auth/login', [AuthController::class, 'login']);
Flight::route('POST /api/auth/2fa/verify', [AuthController::class, 'verify2fa']);
Flight::route('GET /api/auth/me', [AuthController::class, 'me']);

Flight::route('GET /api/egresados', [EgresadoController::class, 'index']);
Flight::route('GET /api/egresados/me', [EgresadoController::class, 'me']);
Flight::route('GET /api/egresados/@cve_egresado', [EgresadoController::class, 'show']);
Flight::route('GET /api/egresados/@cve_egresado/perfil', [EgresadoController::class, 'perfil']);
Flight::route('PUT /api/egresados/@cve_egresado/perfil', [EgresadoController::class, 'actualizarPerfil']);
Flight::route('POST /api/egresados/@cve_egresado/cv', [EgresadoController::class, 'subirCv']);
Flight::route('POST /api/egresados/@cve_egresado/foto', [EgresadoController::class, 'subirFoto']);
Flight::route('GET /api/egresados/@cve_egresado/postulaciones', [EgresadoController::class, 'postulaciones']);
Flight::route('GET /api/egresados/@cve_egresado/evaluaciones', [EgresadoController::class, 'evaluaciones']);
Flight::route('DELETE /api/egresados/@cve_egresado/evaluaciones', [EgresadoController::class, 'resetEvaluaciones']);
Flight::route('GET /api/egresados/@cve_egresado/matching', [EgresadoController::class, 'matching']);

Flight::route('GET /api/empresas', [EmpresaController::class, 'index']);
Flight::route('GET /api/empresas/me', [EmpresaController::class, 'me']);
Flight::route('GET /api/empresas/@cve_empresa', [EmpresaController::class, 'show']);
Flight::route('POST /api/empresas', [EmpresaController::class, 'store']);
Flight::route('PUT /api/empresas/@cve_empresa', [EmpresaController::class, 'update']);
Flight::route('POST /api/empresas/@cve_empresa/foto', [EmpresaController::class, 'subirFoto']);
Flight::route('GET /api/empresas/@cve_empresa/vacantes', [EmpresaController::class, 'vacantes']);
Flight::route('GET /api/empresas/@cve_empresa/candidatos', [EmpresaController::class, 'candidatos']);

Flight::route('POST /api/solicitudes-convenio', [SolicitudConvenioController::class, 'store']);
Flight::route('GET /api/solicitudes-convenio', [SolicitudConvenioController::class, 'index']);
Flight::route('PUT /api/solicitudes-convenio/@cve_solicitud_convenio', [SolicitudConvenioController::class, 'update']);

Flight::route('GET /api/vacantes', [VacanteController::class, 'index']);
Flight::route('GET /api/vacantes/@cve_vacante', [VacanteController::class, 'show']);
Flight::route('POST /api/vacantes', [VacanteController::class, 'store']);
Flight::route('PUT /api/vacantes/@cve_vacante', [VacanteController::class, 'update']);
Flight::route('DELETE /api/vacantes/@cve_vacante', [VacanteController::class, 'destroy']);
Flight::route('GET /api/vacantes/@cve_vacante/candidatos', [VacanteController::class, 'candidatos']);
Flight::route('POST /api/vacantes/@cve_vacante/perfil-idoneo', [VacanteController::class, 'perfilIdoneo']);
Flight::route('PUT /api/vacantes/@cve_vacante/perfil-idoneo', [VacanteController::class, 'actualizarPerfilIdoneo']);

Flight::route('GET /api/tipos-prueba', [EvaluacionController::class, 'tiposPrueba']);
Flight::route('GET /api/evaluaciones/preguntas/@cve_tipo_prueba', [EvaluacionController::class, 'preguntas']);
Flight::route('POST /api/evaluaciones/iniciar', [EvaluacionController::class, 'iniciar']);
Flight::route('POST /api/evaluaciones/@cve_evaluacion/responder', [EvaluacionController::class, 'responder']);
Flight::route('POST /api/evaluaciones/@cve_evaluacion/finalizar', [EvaluacionController::class, 'finalizar']);

Flight::route('POST /api/postulaciones', [PostulacionController::class, 'store']);
Flight::route('GET /api/postulaciones', [PostulacionController::class, 'index']);
Flight::route('GET /api/postulaciones/@cve_postulacion', [PostulacionController::class, 'show']);
Flight::route('PUT /api/postulaciones/@cve_postulacion/estatus', [PostulacionController::class, 'estatus']);
Flight::route('GET /api/vacantes/@cve_vacante/postulaciones', [PostulacionController::class, 'porVacante']);

Flight::route('GET /api/mensajes/egresado/@cve_egresado', [MensajeController::class, 'egresado']);
Flight::route('GET /api/mensajes/empresa/@cve_empresa', [MensajeController::class, 'empresa']);
Flight::route('GET /api/mensajes/vacante/@cve_vacante', [MensajeController::class, 'vacante']);
Flight::route('POST /api/mensajes', [MensajeController::class, 'store']);
Flight::route('PUT /api/mensajes/@cve_mensaje/leido', [MensajeController::class, 'leido']);

Flight::route('GET /api/contrataciones', [ContratacionController::class, 'index']);
Flight::route('GET /api/contrataciones/@cve_contratacion', [ContratacionController::class, 'show']);
Flight::route('GET /api/contrataciones/empresa/@cve_empresa', [ContratacionController::class, 'empresa']);
Flight::route('GET /api/contrataciones/egresado/@cve_egresado', [ContratacionController::class, 'egresado']);
Flight::route('POST /api/contrataciones', [ContratacionController::class, 'store']);
Flight::route('PUT /api/contrataciones/@cve_contratacion/confirmar-egresado', [ContratacionController::class, 'confirmarEgresado']);

Flight::route('GET /api/egresados/@cve_egresado/certificados', [CertificadoController::class, 'egresado']);
Flight::route('POST /api/egresados/@cve_egresado/certificados', [CertificadoController::class, 'store']);
Flight::route('PUT /api/certificados/@cve_certificado', [CertificadoController::class, 'update']);
Flight::route('DELETE /api/certificados/@cve_certificado', [CertificadoController::class, 'destroy']);
Flight::route('PUT /api/certificados/@cve_certificado/validar', [CertificadoController::class, 'validar']);

Flight::route('GET /api/preguntas', [PreguntaController::class, 'index']);
Flight::route('GET /api/preguntas/@cve_pregunta', [PreguntaController::class, 'show']);
Flight::route('POST /api/preguntas', [PreguntaController::class, 'store']);
Flight::route('PUT /api/preguntas/@cve_pregunta', [PreguntaController::class, 'update']);
Flight::route('DELETE /api/preguntas/@cve_pregunta', [PreguntaController::class, 'destroy']);
Flight::route('GET /api/empresas/@cve_empresa/preguntas-tecnicas', [PreguntaController::class, 'tecnicasEmpresa']);
Flight::route('POST /api/empresas/@cve_empresa/preguntas-tecnicas', [PreguntaController::class, 'storeTecnicaEmpresa']);

Flight::route('GET /api/vacantes-nacionales', [VacanteNacionalController::class, 'index']);
Flight::route('GET /api/vacantes-nacionales/@cve_vacante_api', [VacanteNacionalController::class, 'show']);
Flight::route('POST /api/vacantes-nacionales/sincronizar', [VacanteNacionalController::class, 'sincronizar']);

Flight::route('GET /api/mercado-laboral/empresas-vacantes', [LaborMarketController::class, 'empresasVacantes']);
Flight::route('GET /api/denue/empresas', [LaborMarketController::class, 'denueEmpresas']);
Flight::route('GET /api/theirstack/vacantes', [LaborMarketController::class, 'theirStackVacantes']);

Flight::route('GET /api/dashboard/admin/insercion', [DashboardController::class, 'insercion']);
Flight::route('GET /api/dashboard/admin/convenios', [DashboardController::class, 'convenios']);
Flight::route('GET /api/dashboard/admin/competencias', [DashboardController::class, 'competencias']);
Flight::route('GET /api/dashboard/admin/vacantes', [DashboardController::class, 'vacantes']);
Flight::route('GET /api/dashboard/empresa/@cve_empresa', [DashboardController::class, 'empresa']);
Flight::route('GET /api/dashboard/egresado/@cve_egresado', [DashboardController::class, 'egresado']);

Flight::route('GET /api/reportes/insercion/pdf', [ReporteController::class, 'insercionPdf']);
Flight::route('GET /api/reportes/convenios/pdf', [ReporteController::class, 'conveniosPdf']);
Flight::route('GET /api/reportes/egresado/@cve_egresado/pdf', [ReporteController::class, 'egresadoPdf']);
Flight::route('GET /api/reportes/vacantes/excel', [ReporteController::class, 'vacantesExcel']);
