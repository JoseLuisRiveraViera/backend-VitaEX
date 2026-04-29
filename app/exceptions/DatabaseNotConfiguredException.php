<?php
declare(strict_types=1);

namespace app\exceptions;

class DatabaseNotConfiguredException extends HttpException
{
	public function __construct()
	{
		parent::__construct('La base de datos no esta configurada', 503);
	}
}
