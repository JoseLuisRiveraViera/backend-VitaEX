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
			'egresado' => (function () use ($id): array {
				if ($id === null) return ['perfil' => null, 'postulaciones' => [], 'evaluaciones' => []];
				$model = new Egresado();
				// find() hace JOIN con vw_puntaje_egresado e incluye puntajes por dimensión
				$perfil = $model->find($id) ?? $model->perfil($id);
				return [
					'perfil'        => $perfil,
					'postulaciones' => $model->postulaciones($id),
					'evaluaciones'  => $model->evaluaciones($id),
				];
			})(),
			default => [],
		};
	}

	private function html(string $tipo, array $data): string
	{
		if (str_starts_with($tipo, 'egresado-')) {
			return $this->htmlEgresado($data);
		}
		
		$fecha = date('d/m/Y H:i');
		$baseStyles = '
			body { font-family: DejaVu Sans, Arial, sans-serif; margin: 0; padding: 24px; color: #1f2937; font-size: 12px; }
			h1  { font-size: 20px; color: #065f46; margin: 0 0 4px; }
			h2  { font-size: 14px; color: #374151; margin: 16px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
			.header-origin { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #059669; padding-bottom: 12px; }
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
			.footer-origin   { margin-top: 24px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
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

		return $this->htmlGenerico($tipo, $data);
	}

	private function htmlEgresado(array $data): string
	{
		$p = $data['perfil'] ?? [];
		$nombre = htmlspecialchars(trim(
			($p['nombre'] ?? '') . ' ' .
			($p['primer_apellido'] ?? '') . ' ' .
			($p['segundo_apellido'] ?? '')
		));
		$matricula    = htmlspecialchars($p['matricula'] ?? '—');
		$carrera      = htmlspecialchars($p['carrera'] ?? '—');
		$anio         = htmlspecialchars((string) ($p['anio_egreso'] ?? '—'));
		$correo       = htmlspecialchars($p['correo_personal'] ?? $p['correo_institucional'] ?? '—');
		$fecha        = date('d/m/Y');

		$evals = $data['evaluaciones'] ?? [];
		$puntajes = [
			'Psicométrica' => null,
			'Cognitiva'    => null,
			'Técnica'      => null,
			'Proyectiva'   => null,
		];
		
		if (!empty($p['puntaje_psicometrica'])) $puntajes['Psicométrica'] = $p['puntaje_psicometrica'];
		if (!empty($p['puntaje_cognitiva']))    $puntajes['Cognitiva']    = $p['puntaje_cognitiva'];
		if (!empty($p['puntaje_tecnica']))      $puntajes['Técnica']      = $p['puntaje_tecnica'];
		if (!empty($p['puntaje_proyectiva']))   $puntajes['Proyectiva']   = $p['puntaje_proyectiva'];

		$puntajesHtml = '';
		$colores = ['Psicométrica' => '#0d9488', 'Cognitiva' => '#3b82f6', 'Técnica' => '#8b5cf6', 'Proyectiva' => '#f97316'];
		foreach ($puntajes as $dim => $val) {
			$pct  = $val !== null ? (float) $val : 0;
			$text = $val !== null ? number_format($pct, 1) . '%' : 'Pendiente';
			$bar  = $val !== null ? (int) $pct : 0;
			$color = $colores[$dim] ?? '#03837b';
			$puntajesHtml .= "
			<div style='margin-bottom:14px'>
				<div style='display:flex;justify-content:space-between;margin-bottom:4px'>
					<strong style='color:{$color}'>{$dim}</strong>
					<span>{$text}</span>
				</div>
				<div style='background:#e5e7eb;border-radius:4px;height:10px'>
					<div style='background:{$color};width:{$bar}%;height:10px;border-radius:4px'></div>
				</div>
			</div>";
		}

		$posts = $data['postulaciones'] ?? [];
		$postsHtml = '';
		foreach ($posts as $post) {
			$vacante = htmlspecialchars($post['vacante'] ?? $post['titulo'] ?? '—');
			$empresa = htmlspecialchars($post['empresa'] ?? $post['razon_social'] ?? '—');
			$estado  = htmlspecialchars($post['estado'] ?? '—');
			$fecha_p = htmlspecialchars(substr((string)($post['fecha_postulacion'] ?? ''), 0, 10));
			$postsHtml .= "<tr>
				<td style='padding:8px;border-bottom:1px solid #e5e7eb'>{$vacante}</td>
				<td style='padding:8px;border-bottom:1px solid #e5e7eb'>{$empresa}</td>
				<td style='padding:8px;border-bottom:1px solid #e5e7eb'>{$fecha_p}</td>
				<td style='padding:8px;border-bottom:1px solid #e5e7eb'>{$estado}</td>
			</tr>";
		}
		if ($postsHtml === '') {
			$postsHtml = "<tr><td colspan='4' style='padding:12px;text-align:center;color:#9ca3af'>Sin postulaciones registradas</td></tr>";
		}

		return "<!DOCTYPE html>
<html lang='es'>
<head>
<meta charset='utf-8'>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a1a; margin: 0; padding: 32px; }
  .header { background: #033d3c; color: white; padding: 20px 24px; border-radius: 8px; margin-bottom: 24px; }
  .header h1 { margin:0; font-size:20px; }
  .header p  { margin:4px 0 0; font-size:12px; opacity:.8; }
  .section { margin-bottom: 24px; }
  .section h2 { font-size:14px; color:#033d3c; border-bottom:2px solid #03837b; padding-bottom:6px; margin-bottom:12px; }
  .datos-grid { display:table; width:100%; border-collapse:collapse; }
  .dato { display:table-row; }
  .dato-label { display:table-cell; width:30%; font-weight:bold; color:#6b7280; padding:4px 0; font-size:12px; }
  .dato-value { display:table-cell; padding:4px 0; font-size:12px; }
  table { width:100%; border-collapse:collapse; font-size:12px; }
  th { background:#f4f6f8; text-align:left; padding:8px; font-size:11px; text-transform:uppercase; color:#6b7280; }
  .footer { text-align:center; color:#9ca3af; font-size:11px; margin-top:32px; border-top:1px solid #e5e7eb; padding-top:12px; }
</style>
</head>
<body>
  <div class='header'>
    <h1>Reporte de Idoneidad Profesional</h1>
    <p>Universidad Tecnológica de la Costa &nbsp;·&nbsp; VitaeX &nbsp;·&nbsp; Generado el {$fecha}</p>
  </div>

  <div class='section'>
    <h2>Datos del Egresado</h2>
    <div class='datos-grid'>
      <div class='dato'><span class='dato-label'>Nombre completo</span><span class='dato-value'>{$nombre}</span></div>
      <div class='dato'><span class='dato-label'>Matrícula</span><span class='dato-value'>{$matricula}</span></div>
      <div class='dato'><span class='dato-label'>Carrera</span><span class='dato-value'>{$carrera}</span></div>
      <div class='dato'><span class='dato-label'>Año de egreso</span><span class='dato-value'>{$anio}</span></div>
      <div class='dato'><span class='dato-label'>Correo</span><span class='dato-value'>{$correo}</span></div>
    </div>
  </div>

  <div class='section'>
    <h2>Puntajes por Dimensión</h2>
    {$puntajesHtml}
  </div>

  <div class='section'>
    <h2>Historial de Postulaciones</h2>
    <table>
      <tr>
        <th>Vacante</th>
        <th>Empresa</th>
        <th>Fecha</th>
        <th>Estado</th>
      </tr>
      {$postsHtml}
    </table>
  </div>

  <div class='footer'>Documento generado automáticamente por VitaeX — UT de la Costa, Nayarit, México</div>
</body>
</html>";
	}

	private function htmlGenerico(string $tipo, array $data): string
	{
		return '<html><meta charset="utf-8"><body style="font-family:Arial,sans-serif;padding:32px">
			<h1 style="color:#033d3c">Reporte: ' . htmlspecialchars($tipo) . '</h1>
			<pre style="background:#f4f6f8;padding:16px;border-radius:8px;font-size:12px">' .
			htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') .
			'</pre></body></html>';
	}

	private function htmlInsercion(array $rows, string $fecha, string $css): string
	{
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

			$words  = preg_split('/\s+/', $r['carrera'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
			$ignore = ['de', 'del', 'en', 'y', 'la', 'el', 'los', 'las', 'con', 'para'];
			$abbr   = strtoupper(implode('', array_map(
				static fn(string $w): string => $w[0] ?? '',
				array_filter($words ?: [], static fn(string $w): bool => !in_array(strtolower($w), $ignore, true))
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
			<div class='header-origin'>
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
			<div class='footer-origin'>Sistema VitaeX · Universidad Tecnológica de la Costa · Reporte generado el {$fecha}</div>
		</body></html>";
	}

	private function htmlConvenios(array $data, string $fecha, string $css): string
	{
		$items   = $data['items']   ?? $data;
		$resumen = $data['resumen'] ?? [];

		$totalE  = $resumen['total_empresas']       ?? count(array_unique(array_column($items, 'cve_empresa')));
		$conConv = $resumen['empresas_con_convenio'] ?? count(array_filter($items, static fn($r) => ($r['cve_convenio'] ?? null) !== null));
		$sinConv = $resumen['empresas_sin_convenio'] ?? count(array_filter($items, static fn($r) => ($r['cve_convenio'] ?? null) === null));

		$filas = '';
		foreach ($items as $r) {
			$estado = $r['estado'] ?? (($r['cve_convenio'] ?? null) !== null ? 'activo' : 'sin convenio');
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
			<div class='header-origin'>
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
			<div class='footer-origin'>Sistema VitaeX · Universidad Tecnológica de la Costa · Reporte generado el {$fecha}</div>
		</body></html>";

	}
}
