<?php
declare(strict_types=1);

namespace app\controllers;

use app\core\Request;
use app\core\Response;
use app\models\Certificado;
use Throwable;

class CertificadoController
{
	public function egresado(string $cve_egresado): void
	{
		try { Response::success((new Certificado())->porEgresado($cve_egresado), 'Certificados encontrados'); }
		catch (Throwable $e) { Response::exception($e, 'No se pudieron consultar certificados'); }
	}

	public function store(string $cve_egresado): void
	{
		try { Response::success((new Certificado())->create($cve_egresado, Request::body()), 'Certificado creado', 201); }
		catch (Throwable $e) { Response::exception($e, 'No se pudo crear el certificado'); }
	}

	public function update(string $cve_certificado): void
	{
		try {
			$row = (new Certificado())->update($cve_certificado, Request::body());
			$row === null ? Response::error('Certificado no encontrado', [], 404) : Response::success($row, 'Certificado actualizado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo actualizar el certificado'); }
	}

	public function destroy(string $cve_certificado): void
	{
		try {
			$deleted = (new Certificado())->delete($cve_certificado);
			$deleted === 0 ? Response::error('Certificado no encontrado', [], 404) : Response::success(['deleted' => true], 'Certificado eliminado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo eliminar el certificado'); }
	}

	public function validar(string $cve_certificado): void
	{
		try {
			$row = (new Certificado())->validar($cve_certificado);
			$row === null ? Response::error('Certificado no encontrado', [], 404) : Response::success($row, 'Certificado validado');
		} catch (Throwable $e) { Response::exception($e, 'No se pudo validar el certificado'); }
	}
}
