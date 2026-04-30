<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\services\CvPdfService;
use Throwable;

class CvPdfController
{
	public function generate(string $cve_egresado): void
	{
		try {
			if (!is_numeric($cve_egresado)) {
				Response::error('El ID del egresado debe ser numérico', [], 400);
				return;
			}

			$query = Request::query();
			$vacanteId = isset($query['vacante_id']) && is_numeric($query['vacante_id']) 
				? (int) $query['vacante_id'] 
				: null;
			$isDownload = isset($query['download']) && $query['download'] === '1';

			$service = new CvPdfService();
			$pdfContent = $service->generateForEgresado((int) $cve_egresado, $vacanteId);

			if ($pdfContent === null) {
				Response::error('Egresado no encontrado o no se pudo generar el PDF', [], 404);
				return;
			}

			$filename = "cv-egresado-{$cve_egresado}.pdf";
			$disposition = $isDownload ? 'attachment' : 'inline';

			header('Content-Type: application/pdf');
			header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
			
			echo $pdfContent;
		} catch (Throwable $exception) {
			Response::error('Error al generar el PDF del CV', ['detail' => $exception->getMessage()], 500);
		}
	}
}
