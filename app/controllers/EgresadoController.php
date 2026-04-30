<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\AuthMiddleware;
use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\models\Egresado;
use app\services\GoogleDriveService;
use app\services\MatchingService;
use Throwable;

class EgresadoController
{
	public function index(): void
	{
		try {
			Response::success((new Egresado())->all(Request::query()), 'Egresados encontrados');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron listar los egresados');
		}
	}

	public function me(): void
	{
		try {
			$payload = AuthMiddleware::requireRole(['egresado']);
			if ($payload === null) {
				return;
			}

			$model = new Egresado();
			$row = null;
			if (!empty($payload['cve_egresado'])) {
				$row = $model->perfil($payload['cve_egresado']);
			}
			if ($row === null && !empty($payload['cve_persona'])) {
				$egresado = $model->findByPersonaExterna((string) $payload['cve_persona']);
				$row = $egresado === null ? null : $model->perfil($egresado['cve_egresado']);
			}
			if ($row === null && !empty($payload['login_identifier'])) {
				$egresado = $model->findByLoginIdentifier((string) $payload['login_identifier']);
				$row = $egresado === null ? null : $model->perfil($egresado['cve_egresado']);
			}

			$row === null ? Response::error('Egresado autenticado sin registro local', [], 404) : Response::success($row, 'Egresado autenticado');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar el egresado autenticado', ['detail' => $exception->getMessage()], 500);
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

	public function subirCv(string $cve_egresado): void
	{
		try {
			$file = Request::file('file', 'cv', 'documento');
			if ($file === null) {
				Response::error('Archivo no enviado', ['file' => 'Envia el CV como multipart/form-data.'], 422);
				return;
			}

			$drive = (new GoogleDriveService())->uploadToFolder($file, 'GOOGLE_DRIVE_EGRESADO_CV_FOLDER_ID', [
				'prefix' => 'egresado_' . $cve_egresado . '_cv_',
				'allowed_mime_types' => [
					'application/pdf',
					'application/msword',
					'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				],
				'allowed_extensions' => ['pdf', 'doc', 'docx'],
			]);

			$row = (new Egresado())->actualizarPerfil($cve_egresado, ['url_cv' => $drive['url']]);
			if ($row === null) {
				Response::error('Perfil no encontrado', [], 404);
				return;
			}

			$row['drive'] = $drive;
			Response::success($row, 'CV subido a Google Drive');
		} catch (Throwable $exception) {
			Response::error('No se pudo subir el CV', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function subirFoto(string $cve_egresado): void
	{
		try {
			$file = Request::file('file', 'foto', 'imagen');
			if ($file === null) {
				Response::error('Archivo no enviado', ['file' => 'Envia la foto como multipart/form-data.'], 422);
				return;
			}

			$drive = (new GoogleDriveService())->uploadToFolder($file, 'GOOGLE_DRIVE_EGRESADO_FOTO_FOLDER_ID', [
				'prefix' => 'egresado_' . $cve_egresado . '_foto_',
				'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
				'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
			]);

			$row = (new Egresado())->actualizarPerfil($cve_egresado, ['url_foto' => $drive['url']]);
			if ($row === null) {
				Response::error('Perfil no encontrado', [], 404);
				return;
			}

			$row['drive'] = $drive;
			Response::success($row, 'Foto de perfil subida a Google Drive');
		} catch (Throwable $exception) {
			Response::error('No se pudo subir la foto de perfil', ['detail' => $exception->getMessage()], 422);
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

	public function resetEvaluaciones(string $cve_egresado): void
	{
		try {
			$total = (new Egresado())->resetEvaluaciones($cve_egresado);
			Response::success(['eliminadas' => $total], 'Evaluaciones reiniciadas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron reiniciar las evaluaciones', ['detail' => $exception->getMessage()], 500);
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

	public function certificados(string $cve_egresado): void
	{
		try {
			Response::success((new Egresado())->certificados($cve_egresado), 'Certificados encontrados');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron consultar los certificados');
		}
	}
}
