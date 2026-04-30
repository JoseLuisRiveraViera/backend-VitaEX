<?php
declare(strict_types=1);

namespace app\services;

use Throwable;

class RadarChartService
{
	/**
	 * Generates a radar chart using QuickChart.io and returns it as a base64 string.
	 *
	 * @param array $labels
	 * @param array $egresadoScores
	 * @param array|null $idealScores
	 * @return string|null Base64 data URI or null on failure
	 */
	public function generateRadarChart(array $labels, array $egresadoScores, ?array $idealScores = null): ?string
	{
		try {
			if (empty($labels) || empty($egresadoScores)) {
				return null;
			}

			$datasets = [
				[
					'label' => 'Perfil del Egresado',
					'data' => $egresadoScores,
					'backgroundColor' => 'rgba(40, 167, 69, 0.2)',
					'borderColor' => 'rgba(40, 167, 69, 1)',
					'pointBackgroundColor' => 'rgba(40, 167, 69, 1)',
				]
			];

			if ($idealScores !== null && !empty($idealScores)) {
				$datasets[] = [
					'label' => 'Perfil Ideal (Vacante)',
					'data' => $idealScores,
					'backgroundColor' => 'rgba(108, 117, 125, 0.2)',
					'borderColor' => 'rgba(108, 117, 125, 1)',
					'pointBackgroundColor' => 'rgba(108, 117, 125, 1)',
				];
			}

			$chartConfig = [
				'type' => 'radar',
				'data' => [
					'labels' => $labels,
					'datasets' => $datasets
				],
				'options' => [
					'scale' => [
						'ticks' => [
							'beginAtZero' => true,
							'min' => 0,
							'max' => 100,
							'stepSize' => 20
						]
					],
					'legend' => [
						'position' => 'bottom'
					]
				]
			];

			$jsonConfig = json_encode($chartConfig);
			$encodedConfig = urlencode($jsonConfig);
			
			// We use a fixed width and height for consistency in the PDF
			$url = "https://quickchart.io/chart?w=500&h=400&c={$encodedConfig}";

			// Fetch the image
			$context = stream_context_create([
				'http' => [
					'timeout' => 5 // 5 seconds timeout to avoid hanging the PDF generation
				]
			]);
			
			$imageContent = file_get_contents($url, false, $context);
			
			if ($imageContent === false) {
				return null; // Silent failure, handled by the view
			}

			$base64 = base64_encode($imageContent);
			return 'data:image/png;base64,' . $base64;
			
		} catch (Throwable $exception) {
			// If anything fails, return null so the PDF can still render without the chart
			return null;
		}
	}
}
