<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Perfil Profesional</title>
	<style>
		body {
			font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
			font-size: 11pt;
			color: #333;
			line-height: 1.4;
			margin: 0;
			padding: 0;
		}
		.container {
			width: 100%;
			max-width: 800px;
			margin: 0 auto;
		}
		.header {
			text-align: center;
			border-bottom: 2px solid #00563f; /* Institucional UT Costa */
			padding-bottom: 10px;
			margin-bottom: 20px;
		}
		.header img {
			max-width: 120px;
			margin-bottom: 10px;
		}
		.header h1 {
			margin: 0;
			font-size: 18pt;
			color: #00563f;
		}
		.section {
			margin-bottom: 20px;
		}
		.section-title {
			background-color: #f2f2f2;
			color: #00563f;
			padding: 5px 10px;
			font-size: 13pt;
			font-weight: bold;
			border-left: 5px solid #00563f;
			margin-bottom: 10px;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 10px;
		}
		th, td {
			padding: 6px;
			text-align: left;
			vertical-align: top;
		}
		.personal-info th {
			width: 25%;
			color: #555;
		}
		.radar-container {
			text-align: center;
			margin: 20px 0;
		}
		.radar-container img {
			max-width: 400px;
			height: auto;
		}
		.footer {
			position: fixed;
			bottom: -20px;
			left: 0;
			right: 0;
			text-align: center;
			font-size: 9pt;
			color: #777;
			border-top: 1px solid #ccc;
			padding-top: 5px;
		}
		.badge {
			display: inline-block;
			padding: 3px 6px;
			background-color: #00563f;
			color: #fff;
			border-radius: 3px;
			font-size: 9pt;
			margin-right: 5px;
			margin-bottom: 5px;
		}
		.badge-opportunity {
			background-color: #c9302c;
		}
	</style>
</head>
<body>
	<div class="footer">
		Bolsa de Trabajo UT de la Costa - Documento generado automáticamente el <?= date('d/m/Y H:i') ?>
	</div>

	<div class="container">
		<div class="header">
			<?php if (!empty($logoBase64)): ?>
				<img src="<?= $logoBase64 ?>" alt="Logo UT Costa">
			<?php else: ?>
				<h2 style="color: #00563f;">UT de la Costa</h2>
			<?php endif; ?>
			<h1>Perfil Profesional del Egresado</h1>
		</div>

		<div class="section">
			<div class="section-title">Datos Personales</div>
			<table class="personal-info">
				<tr>
					<th>Nombre:</th>
					<td colspan="3">
						<?= htmlspecialchars(($perfil['nombre'] ?? '') . ' ' . ($perfil['primer_apellido'] ?? '') . ' ' . ($perfil['segundo_apellido'] ?? '')) ?>
					</td>
				</tr>
				<tr>
					<th>Matrícula:</th>
					<td><?= htmlspecialchars($perfil['matricula'] ?? 'N/A') ?></td>
					<th>Carrera:</th>
					<td><?= htmlspecialchars($perfil['carrera'] ?? 'N/A') ?></td>
				</tr>
				<tr>
					<th>Correo:</th>
					<td><?= htmlspecialchars($perfil['correo_institucional'] ?? $perfil['correo_personal'] ?? 'N/A') ?></td>
					<th>Teléfono:</th>
					<td><?= htmlspecialchars($perfil['telefono'] ?? 'N/A') ?></td>
				</tr>
				<tr>
					<th>Ubicación:</th>
					<td colspan="3">
						<?= htmlspecialchars(($perfil['municipio'] ?? '') . ', ' . ($perfil['estado_ubicacion'] ?? '')) ?>
					</td>
				</tr>
			</table>
			<?php if (!empty($perfil['resumen_profesional'])): ?>
				<p><strong>Resumen Profesional:</strong><br> <?= nl2br(htmlspecialchars($perfil['resumen_profesional'])) ?></p>
			<?php endif; ?>
		</div>

		<?php if (!empty($habilidades)): ?>
		<div class="section">
			<div class="section-title">Habilidades</div>
			<div>
				<?php foreach ($habilidades as $hab): ?>
					<span class="badge"><?= htmlspecialchars($hab['nombre'] ?? '') ?> (<?= htmlspecialchars($hab['nivel'] ?? '') ?>)</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if (!empty($experiencias)): ?>
		<div class="section">
			<div class="section-title">Experiencia Laboral</div>
			<?php foreach ($experiencias as $exp): ?>
				<div style="margin-bottom: 10px;">
					<strong><?= htmlspecialchars($exp['puesto'] ?? '') ?></strong> en <?= htmlspecialchars($exp['empresa'] ?? '') ?><br>
					<span style="color:#666; font-size:10pt;">
						<?= htmlspecialchars($exp['fecha_inicio'] ?? '') ?> - <?= htmlspecialchars($exp['fecha_fin'] ?? 'Actualidad') ?>
					</span>
					<?php if (!empty($exp['descripcion'])): ?>
						<p style="margin: 5px 0 0 0; font-size:10.5pt;"><?= nl2br(htmlspecialchars($exp['descripcion'])) ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="section" style="page-break-inside: avoid;">
			<div class="section-title">Resultados de Evaluación de Competencias</div>
			<table border="1" style="border: 1px solid #ddd;">
				<tr style="background-color: #f9f9f9;">
					<th>Categoría</th>
					<th>Puntaje Egresado</th>
					<?php if ($vacante): ?>
						<th>Puntaje Ideal (Vacante)</th>
					<?php endif; ?>
				</tr>
				<tr>
					<td>Psicométrica</td>
					<td><?= number_format($egresadoScoresMap['psicometrica'], 2) ?>%</td>
					<?php if ($vacante): ?><td><?= number_format((float)($perfilIdeal['puntaje_psicometrica'] ?? 0), 2) ?>%</td><?php endif; ?>
				</tr>
				<tr>
					<td>Cognitiva</td>
					<td><?= number_format($egresadoScoresMap['cognitiva'], 2) ?>%</td>
					<?php if ($vacante): ?><td><?= number_format((float)($perfilIdeal['puntaje_cognitiva'] ?? 0), 2) ?>%</td><?php endif; ?>
				</tr>
				<tr>
					<td>Técnica</td>
					<td><?= number_format($egresadoScoresMap['tecnica'], 2) ?>%</td>
					<?php if ($vacante): ?><td><?= number_format((float)($perfilIdeal['puntaje_tecnica'] ?? 0), 2) ?>%</td><?php endif; ?>
				</tr>
				<tr>
					<td>Proyectiva</td>
					<td><?= number_format($egresadoScoresMap['proyectiva'], 2) ?>%</td>
					<?php if ($vacante): ?><td><?= number_format((float)($perfilIdeal['puntaje_proyectiva'] ?? 0), 2) ?>%</td><?php endif; ?>
				</tr>
			</table>
			
			<?php if ($radarChartBase64): ?>
				<div class="radar-container">
					<h4>Radar de Competencias</h4>
					<img src="<?= $radarChartBase64 ?>" alt="Gráfico Radar">
				</div>
			<?php endif; ?>
		</div>

		<?php if ($vacante): ?>
		<div class="section" style="page-break-inside: avoid;">
			<div class="section-title">Análisis de Idoneidad: <?= htmlspecialchars($vacante['titulo'] ?? '') ?></div>
			<p>
				<strong>Empresa:</strong> <?= htmlspecialchars($vacante['razon_social'] ?? $vacante['nombre_comercial'] ?? '') ?><br>
				<strong>Porcentaje de Coincidencia General:</strong> 
				<span style="font-size: 14pt; font-weight: bold; color: <?= $idoneidad >= 80 ? '#00563f' : ($idoneidad >= 60 ? '#f0ad4e' : '#d9534f') ?>;">
					<?= number_format($idoneidad ?? 0, 2) ?>%
				</span>
			</p>
			
			<?php if (!empty($fortalezas)): ?>
				<p><strong>Fortalezas (>90% coincidencia):</strong> 
					<?php foreach ($fortalezas as $f): ?>
						<span class="badge"><?= $f ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php if (!empty($areasOportunidad)): ?>
				<p><strong>Áreas de Oportunidad (<70% coincidencia):</strong> 
					<?php foreach ($areasOportunidad as $a): ?>
						<span class="badge badge-opportunity"><?= $a ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

	</div>
</body>
</html>
