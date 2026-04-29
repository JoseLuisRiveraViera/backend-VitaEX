<?php
declare(strict_types=1);

namespace app\controllers;

use app\services\UserService;
use flight\Engine;

class UserController extends BaseController
{
	private UserService $users;

	public function __construct(Engine $app)
	{
		parent::__construct($app);

		$this->users = $app->userService();
	}

	public function index(): void
	{
		$this->run(function (): void {
			$result = $this->users->list($this->query());

			$this->success('Usuarios obtenidos correctamente', $result['items'], 200, $result['meta']);
		});
	}

	public function show(string $id): void
	{
		$this->run(function () use ($id): void {
			$user = $this->users->find((int) $id);

			$this->success('Usuario obtenido correctamente', $user);
		});
	}

	public function store(): void
	{
		$this->run(function (): void {
			$user = $this->users->create($this->body());

			$this->success('Usuario creado correctamente', $user, 201);
		});
	}

	public function update(string $id): void
	{
		$this->run(function () use ($id): void {
			$user = $this->users->update((int) $id, $this->body());

			$this->success('Usuario actualizado correctamente', $user);
		});
	}

	public function destroy(string $id): void
	{
		$this->run(function () use ($id): void {
			$this->users->delete((int) $id);

			$this->success('Usuario eliminado correctamente');
		});
	}
}
