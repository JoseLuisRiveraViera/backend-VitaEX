<?php
declare(strict_types=1);

namespace app\services;

use app\models\Dashboard;
use app\models\Egresado;
use app\models\Reporte;

class ReportExportService
{
	public function pdf(string $tipo, array $data): void
	{
		(new Reporte())->registrar($tipo, 'pdf', ['inline' => true]);
		header('Content-Type: application/pdf');
		header('Content-Disposition: inline; filename="' . $tipo . '.pdf"');

		if (class_exists('\\Dompdf\\Dompdf')) {
			$dompdf = new \Dompdf\Dompdf();
			$dompdf->loadHtml($this->html($tipo, $data));
			$dompdf->render();
			echo $dompdf->output();
			return;
		}

		echo $this->minimalPdf($tipo);
	}

	public function excelVacantes(array $vacantes): void
	{
		(new Reporte())->registrar('vacantes', 'excel', ['inline' => true]);
		if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->fromArray([['cve_vacante', 'titulo', 'empresa', 'estado']], null, 'A1');
			$rowNumber = 2;
			foreach ($vacantes as $row) {
				$sheet->fromArray([[
					$row['cve_vacante'] ?? '',
					$row['titulo'] ?? '',
					$row['razon_social'] ?? '',
					$row['estado'] ?? '',
				]], null, 'A' . $rowNumber);
				$rowNumber++;
			}
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="vacantes.xlsx"');
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
			$writer->save('php://output');
			return;
		}

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="vacantes.csv"');
		$out = fopen('php://output', 'w');
		fputcsv($out, ['cve_vacante', 'titulo', 'empresa', 'estado']);
		foreach ($vacantes as $row) {
			fputcsv($out, [$row['cve_vacante'] ?? '', $row['titulo'] ?? '', $row['razon_social'] ?? '', $row['estado'] ?? '']);
		}
		fclose($out);
	}

	public function dataFor(string $tipo, ?string $id = null): array
	{
		$dashboard = new Dashboard();
		return match ($tipo) {
			'insercion' => $dashboard->insercion(),
			'convenios' => $dashboard->convenios(),
			'egresado' => [
				'perfil' => $id === null ? null : (new Egresado())->perfil($id),
				'postulaciones' => $id === null ? [] : (new Egresado())->postulaciones($id),
				'evaluaciones' => $id === null ? [] : (new Egresado())->evaluaciones($id),
			],
			default => [],
		};
	}

	private function html(string $tipo, array $data): string
	{
		return '<html><meta charset="utf-8"><body><h1>Reporte ' . htmlspecialchars($tipo) . '</h1><pre>' .
			htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') .
			'</pre></body></html>';
	}

	private function minimalPdf(string $title): string
	{
		$text = 'Reporte ' . $title . ' generado. Instala dompdf/dompdf para PDF enriquecido.';
		return "%PDF-1.1\n1 0 obj<<>>endobj\n2 0 obj<< /Length " . strlen($text) . " >>stream\n" . $text . "\nendstream endobj\ntrailer<<>>\n%%EOF";
	}
}
