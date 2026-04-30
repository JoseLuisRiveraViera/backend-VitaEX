<?php
declare(strict_types=1);

namespace app\models;

class UsuarioSiest extends BaseModel
{
	public function findLogin(string $usuario): ?array
	{
		if ($this->hasTable('usuario_siest') === false) {
			return null;
		}

		return $this->fetchOne(
			'SELECT *
			FROM vw_usuario_siest_login
			WHERE nombre_usuario = :usuario AND activo = true',
			['usuario' => $usuario]
		);
	}
}
