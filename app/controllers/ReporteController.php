<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Response;
use app\models\Vacante;
use app\services\ReportExportService;
use Throwable;

class ReporteController
{
	public function insercionPdf(): void
	{
		try {
			$service = new ReportExportService();
			$service->pdf('insercion', $service->dataFor('insercion'));
		} catch (Throwable $e) { Response::exception($e, 'No se pudo generar el reporte'); }
	}

	public function conveniosPdf(): void
	{
		try {
			$service = new ReportExportService();
			$service->pdf('convenios', $service->dataFor('convenios'));
		} catch (Throwable $e) { Response::exception($e, 'No se pudo generar el reporte'); }
	}

	public function egresadoPdf(string $cve_egresado): void
	{
		try {
			$service = new ReportExportService();
			$service->pdf('egresado-' . $cve_egresado, $service->dataFor('egresado', $cve_egresado));
		} catch (Throwable $e) { Response::exception($e, 'No se pudo generar el reporte'); }
	}

	public function vacantesExcel(): void
	{
		try {
			$data = (new Vacante())->all(['page' => 1, 'limit' => 100]);
			(new ReportExportService())->excelVacantes($data['items'] ?? []);
		} catch (Throwable $e) { Response::exception($e, 'No se pudo generar el reporte'); }
	}
}
