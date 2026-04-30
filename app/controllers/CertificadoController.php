<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Certificado;
use app\services\GoogleDriveService;
use Throwable;

class CertificadoController
{
	public function egresado(string $cve_egresado): void
	{
		try { Response::success((new Certificado())->porEgresado($cve_egresado), 'Certificados encontrados'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar certificados'); }
	}

	public function store(string $cve_egresado): void
	{
		try {
			$file = Request::file('file', 'certificado', 'documento');
			if ($file === null) {
				Response::error('Archivo no enviado', ['file' => 'Envia el certificado como multipart/form-data.'], 422);
				return;
			}

			$form = Request::form();
			$drive = (new GoogleDriveService())->uploadToFolder($file, 'GOOGLE_DRIVE_CERTIFICADOS_FOLDER_ID', [
				'prefix' => 'egresado_' . $cve_egresado . '_certificado_',
				'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
				'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
			]);

			$row = (new Certificado())->create($cve_egresado, [
				'tipo_documento' => trim((string) ($form['tipo_documento'] ?? 'certificado')) ?: 'certificado',
				'nombre_archivo' => trim((string) ($form['nombre_archivo'] ?? $file['name'])) ?: $drive['name'],
				'url_documento' => $drive['url'],
				'fecha_emision' => $this->optionalDate($form['fecha_emision'] ?? null),
				'fecha_vencimiento' => $this->optionalDate($form['fecha_vencimiento'] ?? null),
			]);
			$row['drive'] = $drive;

			Response::success($row, 'Certificado subido a Google Drive', 201);
		} catch (Throwable $e) { Response::exception($e, 'No se pudo crear el certificado'); }
	}

	public function update(string $cve_certificado): void
	{
		try {
			$row = (new Certificado())->update($cve_certificado, Request::body());
			$row === null ? Response::error('Certificado no encontrado', [], 404) : Response::success($row, 'Certificado actualizado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo actualizar el certificado'); }
	}

	public function destroy(string $cve_certificado): void
	{
		try {
			$deleted = (new Certificado())->delete($cve_certificado);
			$deleted === 0 ? Response::error('Certificado no encontrado', [], 404) : Response::success(['deleted' => true], 'Certificado eliminado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo eliminar el certificado'); }
	}

	public function validar(string $cve_certificado): void
	{
		try {
			$row = (new Certificado())->validar($cve_certificado);
			$row === null ? Response::error('Certificado no encontrado', [], 404) : Response::success($row, 'Certificado validado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo validar el certificado'); }
	}

	private function optionalDate(mixed $value): ?string
	{
		$value = is_scalar($value) ? trim((string) $value) : '';
		return $value === '' ? null : $value;
	}
}
