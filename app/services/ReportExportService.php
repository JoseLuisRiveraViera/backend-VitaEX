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
		try { (new Reporte())->registrar($tipo, 'pdf', ['inline' => true]); } catch (\Throwable) { /* tabla puede no existir aún */ }

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
		$fecha = date('d/m/Y H:i');
		$baseStyles = '
			body { font-family: DejaVu Sans, Arial, sans-serif; margin: 0; padding: 24px; color: #1f2937; font-size: 12px; }
			h1  { font-size: 20px; color: #065f46; margin: 0 0 4px; }
			h2  { font-size: 14px; color: #374151; margin: 16px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
			.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #059669; padding-bottom: 12px; }
			.header-left h1 { font-size: 18px; }
			.header-left p  { margin: 2px 0; color: #6b7280; font-size: 10px; }
			.badge { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: bold; }
			table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
			thead tr { background: #059669; color: #fff; }
			thead th { padding: 8px 10px; text-align: left; font-size: 11px; }
			tbody tr:nth-child(even) { background: #f0fdf4; }
			tbody td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
			.kpi-grid { display: flex; gap: 12px; margin-bottom: 16px; }
			.kpi-box  { flex: 1; border: 1px solid #d1fae5; border-radius: 8px; padding: 10px 14px; background: #f0fdf4; }
			.kpi-box .val { font-size: 22px; font-weight: bold; color: #059669; }
			.kpi-box .lbl { font-size: 10px; color: #6b7280; }
			.bar-row  { margin: 6px 0; }
			.bar-label{ font-size: 11px; margin-bottom: 2px; }
			.bar-track{ background: #e5e7eb; border-radius: 4px; height: 12px; }
			.bar-fill { background: #059669; border-radius: 4px; height: 12px; }
			.tag-low  { color: #dc2626; font-weight: bold; }
			.tag-med  { color: #d97706; font-weight: bold; }
			.tag-high { color: #059669; font-weight: bold; }
			.footer   { margin-top: 24px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
			.pill-activo   { background:#d1fae5; color:#065f46; padding:1px 6px; border-radius:20px; font-size:10px; }
			.pill-inactivo { background:#fee2e2; color:#991b1b; padding:1px 6px; border-radius:20px; font-size:10px; }
			.pill-pendiente{ background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:20px; font-size:10px; }
		';

		if ($tipo === 'insercion') {
			return $this->htmlInsercion($data, $fecha, $baseStyles);
		}
		if ($tipo === 'convenios') {
			return $this->htmlConvenios($data, $fecha, $baseStyles);
		}
		if (str_starts_with($tipo, 'egresado-')) {
			return $this->htmlEgresado($data, $fecha, $baseStyles);
		}

		// Fallback genérico limpio
		$json = htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '');
		return "<html><head><meta charset='utf-8'><style>{$baseStyles}</style></head><body>
			<h1>Reporte {$tipo}</h1><pre style='font-size:10px;background:#f9fafb;padding:12px;border-radius:6px;'>{$json}</pre>
			<div class='footer'>Generado el {$fecha} · Sistema VitaeX</div>
		</body></html>";
	}

	private function htmlInsercion(array $rows, string $fecha, string $css): string
	{
		// Columnas reales de vw_insercion_laboral_por_carrera (DDL):
		// carrera, total_egresado_registrado, total_contratado, porcentaje_insercion_laboral

		$totalEgresados  = array_sum(array_column($rows, 'total_egresado_registrado'));
		$totalInsertados = array_sum(array_column($rows, 'total_contratado'));
		$tasaGlobal      = $totalEgresados > 0 ? round($totalInsertados / $totalEgresados * 100, 1) : 0;

		$filas = '';
		foreach ($rows as $r) {
			$tasa     = (float)($r['porcentaje_insercion_laboral'] ?? 0);
			$clase    = $tasa >= 75 ? 'tag-high' : ($tasa >= 50 ? 'tag-med' : 'tag-low');
			$ancho    = min(100, max(0, (int)$tasa));
			$carrera  = htmlspecialchars($r['carrera'] ?? '—');
			$total    = (int)($r['total_egresado_registrado'] ?? 0);
			$contrat  = (int)($r['total_contratado'] ?? 0);

			// Generar abreviatura desde siglas del nombre de carrera
			$words  = preg_split('/\s+/', $r['carrera'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
			$ignore = ['de', 'del', 'en', 'y', 'la', 'el', 'los', 'las', 'con', 'para'];
			$abbr   = strtoupper(implode('', array_map(
				static fn(string $w): string => $w[0],
				array_filter($words ?? [], static fn(string $w): bool => !in_array(strtolower($w), $ignore, true))
			)));

			$filas .= "
			<tr>
				<td><strong>{$abbr}</strong></td>
				<td>{$carrera}</td>
				<td style='text-align:center'>{$total}</td>
				<td style='text-align:center'>{$contrat}</td>
				<td style='text-align:center'>
					<div class='bar-row'>
						<div class='bar-track'><div class='bar-fill' style='width:{$ancho}%'></div></div>
					</div>
				</td>
				<td style='text-align:center'><span class='{$clase}'>{$tasa}%</span></td>
			</tr>";
		}

		return "<html><head><meta charset='utf-8'><style>{$css}</style></head><body>
			<div class='header'>
				<div class='header-left'>
					<h1>Reporte de Inserción Laboral por Carrera</h1>
					<p>Universidad Tecnológica de la Costa · Bolsa de Trabajo VitaeX</p>
					<p>Generado el {$fecha}</p>
				</div>
				<span class='badge'>INSTITUCIONAL</span>
			</div>
			<div class='kpi-grid'>
				<div class='kpi-box'><div class='val'>{$totalEgresados}</div><div class='lbl'>Egresados registrados</div></div>
				<div class='kpi-box'><div class='val'>{$totalInsertados}</div><div class='lbl'>Egresados insertados</div></div>
				<div class='kpi-box'><div class='val'>{$tasaGlobal}%</div><div class='lbl'>Tasa global de inserción</div></div>
				<div class='kpi-box'><div class='val'>" . count($rows) . "</div><div class='lbl'>Carreras analizadas</div></div>
			</div>
			<h2>Detalle por Carrera</h2>
			<table>
				<thead><tr>
					<th>Abrev.</th><th>Carrera</th><th>Total egresados</th><th>Insertados</th><th>Progreso</th><th>Tasa</th>
				</tr></thead>
				<tbody>{$filas}</tbody>
			</table>
			<div class='footer'>Sistema VitaeX · Universidad Tecnológica de la Costa · Reporte generado el {$fecha}</div>
		</body></html>";
	}

	private function htmlConvenios(array $data, string $fecha, string $css): string
	{
		// vw_convenio_por_empresa: razon_social, nombre_comercial, zona, cve_convenio,
		// numero_convenio, fecha_inicio, fecha_fin, estado, vencido, dias_para_vencer
		$items   = $data['items']   ?? $data;
		$resumen = $data['resumen'] ?? [];

		// Calcular KPIs desde los items si no vienen en resumen
		$totalE  = $resumen['total_empresas']       ?? count(array_unique(array_column($items, 'cve_empresa')));
		$conConv = $resumen['empresas_con_convenio'] ?? count(array_filter($items, static fn($r) => $r['cve_convenio'] !== null));
		$sinConv = $resumen['empresas_sin_convenio'] ?? count(array_filter($items, static fn($r) => $r['cve_convenio'] === null));

		$filas = '';
		foreach ($items as $r) {
			// La vista usa columna 'estado' (tipo estado_convenio: pendiente, activo, por_vencer, vencido, cancelado)
			$estado = $r['estado'] ?? ($r['cve_convenio'] !== null ? 'activo' : 'sin convenio');
			$cls    = match(strtolower((string)$estado)) {
				'activo'     => 'pill-activo',
				'vencido', 'cancelado' => 'pill-inactivo',
				default      => 'pill-pendiente',
			};
			$razon  = htmlspecialchars($r['razon_social'] ?? '—');
			$zona   = htmlspecialchars(str_replace('_', ' ', $r['zona'] ?? '—'));
			$inicio = htmlspecialchars($r['fecha_inicio'] ? date('d/m/Y', strtotime($r['fecha_inicio'])) : '—');
			$fin    = htmlspecialchars($r['fecha_fin']    ? date('d/m/Y', strtotime($r['fecha_fin']))    : '—');
			$filas .= "<tr>
				<td>{$razon}</td><td>{$zona}</td>
				<td><span class='{$cls}'>{$estado}</span></td>
				<td>{$inicio}</td><td>{$fin}</td>
			</tr>";
		}

		return "<html><head><meta charset='utf-8'><style>{$css}</style></head><body>
			<div class='header'>
				<div class='header-left'>
					<h1>Reporte de Convenios Empresariales</h1>
					<p>Universidad Tecnológica de la Costa · Bolsa de Trabajo VitaeX</p>
					<p>Generado el {$fecha}</p>
				</div>
				<span class='badge'>INSTITUCIONAL</span>
			</div>
			<div class='kpi-grid'>
				<div class='kpi-box'><div class='val'>{$totalE}</div><div class='lbl'>Total empresas</div></div>
				<div class='kpi-box'><div class='val'>{$conConv}</div><div class='lbl'>Con convenio</div></div>
				<div class='kpi-box'><div class='val'>{$sinConv}</div><div class='lbl'>Sin convenio</div></div>
			</div>
			<h2>Detalle por Empresa</h2>
			<table>
				<thead><tr><th>Empresa</th><th>Zona</th><th>Estado</th><th>Inicio</th><th>Fin</th></tr></thead>
				<tbody>{$filas}</tbody>
			</table>
			<div class='footer'>Sistema VitaeX · Universidad Tecnológica de la Costa · Reporte generado el {$fecha}</div>
		</body></html>";
	}

	private function htmlEgresado(array $data, string $fecha, string $css): string
	{
		$p    = $data['perfil'] ?? [];
		$psts = $data['postulaciones'] ?? [];
		$evals= $data['evaluaciones'] ?? [];

		$nombre = htmlspecialchars(
			trim(($p['nombre'] ?? '') . ' ' . ($p['primer_apellido'] ?? '') . ' ' . ($p['segundo_apellido'] ?? ''))
		);
		$carrera = htmlspecialchars($p['carrera'] ?? '—');
		$email   = htmlspecialchars($p['email']   ?? '—');
		$cveCve  = htmlspecialchars($p['cve_egresado'] ?? '—');

		$filasPosts = '';
		foreach ($psts as $ps) {
			$est  = htmlspecialchars($ps['estado'] ?? '—');
			$cls  = match(strtolower($ps['estado'] ?? '')) { 'aceptado' => 'pill-activo', 'rechazado' => 'pill-inactivo', default => 'pill-pendiente' };
			$filasPosts .= "<tr>
				<td>{$ps['cve_postulacion']}</td>
				<td>{$ps['cve_vacante']}</td>
				<td><span class='{$cls}'>{$est}</span></td>
				<td>" . htmlspecialchars($ps['fecha_postulacion'] ?? '—') . "</td>
			</tr>";
		}

		$filasEvals = '';
		foreach ($evals as $ev) {
			$filasEvals .= "<tr>
				<td>" . htmlspecialchars($ev['tipo_prueba'] ?? $ev['cve_tipo_prueba'] ?? '—') . "</td>
				<td>" . htmlspecialchars((string)($ev['puntaje'] ?? '—')) . "</td>
				<td>" . htmlspecialchars($ev['fecha_evaluacion'] ?? '—') . "</td>
			</tr>";
		}

		return "<html><head><meta charset='utf-8'><style>{$css}</style></head><body>
			<div class='header'>
				<div class='header-left'>
					<h1>Perfil del Egresado</h1>
					<p>Universidad Tecnológica de la Costa · Bolsa de Trabajo VitaeX</p>
					<p>Generado el {$fecha}</p>
				</div>
				<span class='badge'>CONFIDENCIAL</span>
			</div>
			<div class='kpi-grid'>
				<div class='kpi-box'><div class='val' style='font-size:14px'>{$nombre}</div><div class='lbl'>Nombre</div></div>
				<div class='kpi-box'><div class='val' style='font-size:14px'>{$carrera}</div><div class='lbl'>Carrera</div></div>
				<div class='kpi-box'><div class='val' style='font-size:12px'>{$email}</div><div class='lbl'>Email</div></div>
				<div class='kpi-box'><div class='val'>{$cveCve}</div><div class='lbl'>Cve. Egresado</div></div>
			</div>
			<h2>Postulaciones</h2>
			" . (empty($psts) ? "<p style='color:#9ca3af'>Sin postulaciones registradas.</p>" : "
			<table>
				<thead><tr><th>#</th><th>Vacante</th><th>Estado</th><th>Fecha</th></tr></thead>
				<tbody>{$filasPosts}</tbody>
			</table>") . "
			<h2>Evaluaciones</h2>
			" . (empty($evals) ? "<p style='color:#9ca3af'>Sin evaluaciones registradas.</p>" : "
			<table>
				<thead><tr><th>Tipo</th><th>Puntaje</th><th>Fecha</th></tr></thead>
				<tbody>{$filasEvals}</tbody>
			</table>") . "
			<div class='footer'>Sistema VitaeX · Universidad Tecnológica de la Costa · Reporte generado el {$fecha}</div>
		</body></html>";
	}

	private function minimalPdf(string $title): string
	{
		$text = 'Reporte ' . $title . ' generado. Instala dompdf/dompdf para PDF enriquecido.';
		return "%PDF-1.1\n1 0 obj<<>>endobj\n2 0 obj<< /Length " . strlen($text) . " >>stream\n" . $text . "\nendstream endobj\ntrailer<<>>\n%%EOF";
	}
}
