<?php
declare(strict_types=1);

namespace app\exceptions;

class ValidationException extends HttpException
{
	/**
	 * @param array<string, mixed> $errors
	 */
	public function __construct(array $errors, string $message = 'Datos invalidos')
	{
		parent::__construct($message, 422, $errors);
	}
}
