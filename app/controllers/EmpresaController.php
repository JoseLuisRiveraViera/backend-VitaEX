<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\AuthMiddleware;
use app\core\Request;
use app\core\Response;
use app\core\Validator;
use app\models\Empresa;
use app\services\GoogleDriveService;
use Throwable;

class EmpresaController
{
	public function index(): void
	{
		try {
			Response::success((new Empresa())->all(Request::query()), 'Empresas encontradas');
		} catch (Throwable $exception) {
			Response::exception($exception, 'No se pudieron listar las empresas');
		}
	}

	public function me(): void
	{
		try {
			$payload = AuthMiddleware::requireRole(['empresa']);
			if ($payload === null) {
				return;
			}

			$model = new Empresa();
			$row = null;
			if (!empty($payload['cve_empresa'])) {
				$row = $model->find($payload['cve_empresa']);
			}
			if ($row === null && !empty($payload['cve_persona'])) {
				$row = $model->findByPersonaExterna((string) $payload['cve_persona']);
			}
			if ($row === null && !empty($payload['login_identifier'])) {
				$row = $model->findByLoginIdentifier((string) $payload['login_identifier']);
			}

			$row === null ? Response::error('Empresa autenticada sin registro local', [], 404) : Response::success($row, 'Empresa autenticada');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar la empresa autenticada', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function show(string $cve_empresa): void
	{
		try {
			$row = (new Empresa())->find($cve_empresa);
			$row === null ? Response::error('Empresa no encontrada', [], 404) : Response::success($row, 'Empresa encontrada');
		} catch (Throwable $exception) {
			Response::error('No se pudo consultar la empresa', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function store(): void
	{
		try {
			$body = Request::body();
			$errors = Validator::notBlankWhenPresent($body, ['url_foto']);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			Response::success((new Empresa())->create($body), 'Empresa creada', 201);
		} catch (Throwable $exception) {
			Response::error('No se pudo crear la empresa', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function update(string $cve_empresa): void
	{
		try {
			$body = Request::body();
			$errors = Validator::notBlankWhenPresent($body, ['url_foto']);
			if ($errors !== []) {
				Response::error('Datos inválidos', $errors, 422);
				return;
			}

			$row = (new Empresa())->update($cve_empresa, $body);
			$row === null ? Response::error('Empresa no encontrada', [], 404) : Response::success($row, 'Empresa actualizada');
		} catch (Throwable $exception) {
			Response::error('No se pudo actualizar la empresa', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function subirFoto(string $cve_empresa): void
	{
		try {
			$file = Request::file('file', 'foto', 'logo', 'imagen');
			if ($file === null) {
				Response::error('Archivo no enviado', ['file' => 'Envia la foto o logo como multipart/form-data.'], 422);
				return;
			}

			$drive = (new GoogleDriveService())->uploadToFolder($file, 'GOOGLE_DRIVE_EMPRESA_FOTO_FOLDER_ID', [
				'prefix' => 'empresa_' . $cve_empresa . '_foto_',
				'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
				'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
			]);

			$row = (new Empresa())->update($cve_empresa, ['url_foto' => $drive['url']]);
			if ($row === null) {
				Response::error('Empresa no encontrada', [], 404);
				return;
			}

			$row['drive'] = $drive;
			Response::success($row, 'Foto de empresa subida a Google Drive');
		} catch (Throwable $exception) {
			Response::error('No se pudo subir la foto de empresa', ['detail' => $exception->getMessage()], 422);
		}
	}

	public function vacantes(string $cve_empresa): void
	{
		try {
			Response::success((new Empresa())->vacantes($cve_empresa), 'Vacantes de empresa encontradas');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar las vacantes de la empresa', ['detail' => $exception->getMessage()], 500);
		}
	}

	public function candidatos(string $cve_empresa): void
	{
		try {
			Response::success((new Empresa())->candidatos($cve_empresa), 'Candidatos encontrados');
		} catch (Throwable $exception) {
			Response::error('No se pudieron consultar los candidatos', ['detail' => $exception->getMessage()], 500);
		}
	}
}
