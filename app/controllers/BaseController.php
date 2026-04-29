<?php
declare(strict_types=1);

namespace app\controllers;

use app\support\ApiErrorHandler;
use app\support\ApiResponse;
use flight\Engine;
use Throwable;

abstract class BaseController
{
	protected Engine $app;

	public function __construct(Engine $app)
	{
		$this->app = $app;
	}

	/**
	 * @param mixed $data
	 * @param array<string, mixed> $meta
	 */
	protected function success(string $message, $data = null, int $status = 200, array $meta = []): void
	{
		ApiResponse::success($this->app, $message, $data, $status, $meta);
	}

	protected function run(callable $action): void
	{
		try {
			$action();
		} catch (Throwable $exception) {
			ApiErrorHandler::handle($this->app, $exception);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function body(): array
	{
		return $this->app->request()->data->getData();
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function query(): array
	{
		return $this->app->request()->query->getData();
	}
}
