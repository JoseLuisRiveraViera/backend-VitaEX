<?php
declare(strict_types=1);

namespace app\models;

class SolicitudConvenio extends BaseModel
{
	public function all(): array
	{
		return $this->fetchAll('SELECT * FROM solicitud_convenio ORDER BY cve_solicitud DESC');
	}

	public function create(array $data): array
	{
		return $this->insert('solicitud_convenio', $this->filterTableData('solicitud_convenio', $data, ['cve_solicitud']), 'cve_solicitud');
	}

	public function update(string|int $cveSolicitud, array $data): ?array
	{
		return $this->updateById('solicitud_convenio', 'cve_solicitud', $cveSolicitud, $this->filterTableData('solicitud_convenio', $data, ['cve_solicitud']));
	}
}
