<?php
declare(strict_types=1);

namespace app\exceptions;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
	protected int $statusCode;

	/** @var array<string, mixed> */
	protected array $errors;

	/**
	 * @param array<string, mixed> $errors
	 */
	public function __construct(string $message, int $statusCode = 400, array $errors = [], ?Throwable $previous = null)
	{
		parent::__construct($message, $statusCode, $previous);

		$this->statusCode = $statusCode;
		$this->errors = $errors;
	}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}
}
