<?php
declare(strict_types=1);

namespace app\models;

class Reporte extends BaseModel
{
	public function registrar(string $tipo, string $formato, array $parametros = [], ?string $url = null, ?int $admin = null): array
	{
		return $this->insert('reporte_exportacion', [
			'tipo_reporte' => $tipo,
			'formato' => $formato,
			'parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'url_archivo' => $url,
			'cve_administrador_ut' => $admin,
		], 'cve_reporte_exportacion');
	}
}
