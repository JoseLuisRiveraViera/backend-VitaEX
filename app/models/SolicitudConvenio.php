<?php
declare(strict_types=1);

namespace app\models;

class SolicitudConvenio extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll('SELECT * FROM solicitud_convenio ORDER BY cve_solicitud_convenio DESC');
	}

	public function create(array $data): array
	{
		return $this->insert('solicitud_convenio', $this->filterTableData('solicitud_convenio', $data, ['cve_solicitud_convenio']), 'cve_solicitud_convenio');
	}

	public function update(string|int $cveSolicitud, array $data): ?array
	{
		return $this->updateById('solicitud_convenio', 'cve_solicitud_convenio', $cveSolicitud, $this->filterTableData('solicitud_convenio', $data, ['cve_solicitud_convenio', 'cve_empresa']));
	}
}
