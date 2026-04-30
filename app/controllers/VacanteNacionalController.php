<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\VacanteNacional;
use app\services\ExternalJobsService;
use Throwable;

class VacanteNacionalController
{
	public function index(): void
	{
		try { Response::success((new VacanteNacional())->all(Request::query()), 'Vacantes nacionales encontradas'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar vacantes nacionales'); }
	}

	public function sincronizar(): void
	{
		try { Response::success((new ExternalJobsService())->sync(), 'Sincronización finalizada'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudo sincronizar vacantes nacionales'); }
	}
}
