<?php
declare(strict_types=1);

namespace app\controllers;

class HealthController extends BaseController
{
	public function index(): void
	{
		$this->run(function (): void {
			$this->success('API funcionando correctamente', [
				'service' => $this->app->get('app.name') ?? 'backend-VitaEX',
				'environment' => $this->app->get('app.env') ?? 'local',
				'database' => $this->app->get('database.enabled') === true ? 'configured' : 'not_configured',
				'time' => date(DATE_ATOM),
			]);
		});
	}
}
