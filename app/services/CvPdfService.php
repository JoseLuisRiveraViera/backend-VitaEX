<?php
declare(strict_types=1);

namespace app\services;

use app\models\CvEgresadoRepository;
use app\models\CvVacanteRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

class CvPdfService
{
	private CvEgresadoRepository $egresadoRepo;
	private CvVacanteRepository $vacanteRepo;
	private RadarChartService $radarChartService;

	public function __construct()
	{
		$this->egresadoRepo = new CvEgresadoRepository();
		$this->vacanteRepo = new CvVacanteRepository();
		$this->radarChartService = new RadarChartService();
	}

	public function generateForEgresado(int $egresadoId, ?int $vacanteId = null): ?string
	{
		$perfil = $this->egresadoRepo->findProfileById($egresadoId);
		if (!$perfil) {
			return null;
		}

		$experiencias = $this->egresadoRepo->findExperiencesByEgresadoId($egresadoId);
		$habilidades = $this->egresadoRepo->findSkillsByEgresadoId($egresadoId);
		$resultadosPruebas = $this->egresadoRepo->findTestResultsByEgresadoId($egresadoId);
		$certificaciones = $this->egresadoRepo->findCertificationsByEgresadoId($egresadoId);

		$vacante = null;
		$perfilIdeal = null;
		$idoneidad = null;
		$fortalezas = [];
		$areasOportunidad = [];

		// Default radar labels and scores based on tests
		$radarLabels = ['Psicométrica', 'Cognitiva', 'Técnica', 'Proyectiva'];
		
		$egresadoScoresMap = [
			'psicometrica' => 0,
			'cognitiva' => 0,
			'tecnica' => 0,
			'proyectiva' => 0
		];

		foreach ($resultadosPruebas as $res) {
			$cat = strtolower($res['categoria'] ?? '');
			if (isset($egresadoScoresMap[$cat])) {
				$egresadoScoresMap[$cat] = (float) $res['puntaje_obtenido'];
			}
		}

		$egresadoScores = [
			$egresadoScoresMap['psicometrica'],
			$egresadoScoresMap['cognitiva'],
			$egresadoScoresMap['tecnica'],
			$egresadoScoresMap['proyectiva']
		];

		$idealScores = null;

		if ($vacanteId !== null) {
			$vacante = $this->vacanteRepo->findById($vacanteId);
			if ($vacante) {
				$perfilIdeal = $this->vacanteRepo->findIdealCompetenciesByVacanteId($vacanteId);
				if ($perfilIdeal) {
					$idealMap = [
						'psicometrica' => (float) ($perfilIdeal['puntaje_psicometrica'] ?? 0),
						'cognitiva' => (float) ($perfilIdeal['puntaje_cognitiva'] ?? 0),
						'tecnica' => (float) ($perfilIdeal['puntaje_tecnica'] ?? 0),
						'proyectiva' => (float) ($perfilIdeal['puntaje_proyectiva'] ?? 0),
					];

					$idealScores = [
						$idealMap['psicometrica'],
						$idealMap['cognitiva'],
						$idealMap['tecnica'],
						$idealMap['proyectiva']
					];

					// Calculate idoneidad
					$sum = 0;
					$count = 0;
					$categorias = ['psicometrica', 'cognitiva', 'tecnica', 'proyectiva'];
					
					foreach ($categorias as $cat) {
						if ($idealMap[$cat] > 0) {
							$match = min($egresadoScoresMap[$cat] / $idealMap[$cat], 1) * 100;
							$sum += $match;
							$count++;

							if ($match >= 90) {
								$fortalezas[] = ucfirst($cat);
							} elseif ($match < 70) {
								$areasOportunidad[] = ucfirst($cat);
							}
						}
					}
					
					$idoneidad = $count > 0 ? round($sum / $count, 2) : null;
				}
			}
		}

		$radarChartBase64 = $this->radarChartService->generateRadarChart($radarLabels, $egresadoScores, $idealScores);

		$logoPath = dirname(__DIR__, 2) . '/public/assets/img/logo-ut-costa.png';
		$logoBase64 = null;
		if (file_exists($logoPath)) {
			$logoData = file_get_contents($logoPath);
			$logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
		}

		$html = $this->renderHtmlView([
			'perfil' => $perfil,
			'experiencias' => $experiencias,
			'habilidades' => $habilidades,
			'resultadosPruebas' => $resultadosPruebas,
			'certificaciones' => $certificaciones,
			'vacante' => $vacante,
			'idoneidad' => $idoneidad,
			'fortalezas' => $fortalezas,
			'areasOportunidad' => $areasOportunidad,
			'radarChartBase64' => $radarChartBase64,
			'logoBase64' => $logoBase64,
			'egresadoScoresMap' => $egresadoScoresMap,
			'perfilIdeal' => $perfilIdeal
		]);

		return $this->generatePdf($html);
	}

	private function renderHtmlView(array $data): string
	{
		extract($data);
		ob_start();
		$viewPath = dirname(__DIR__) . '/views/pdf/cv_egresado.php';
		if (file_exists($viewPath)) {
			include $viewPath;
		} else {
			echo "<h1>Error: Plantilla no encontrada</h1>";
		}
		return ob_get_clean() ?: '';
	}

	private function generatePdf(string $html): string
	{
		$options = new Options();
		$options->set('isRemoteEnabled', true);
		$options->set('isHtml5ParserEnabled', true);
		$options->set('defaultFont', 'Helvetica');

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('letter', 'portrait');
		$dompdf->render();

		return $dompdf->output();
	}
}
