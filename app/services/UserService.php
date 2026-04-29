<?php
declare(strict_types=1);

namespace app\services;

use app\exceptions\DatabaseNotConfiguredException;
use app\exceptions\HttpException;
use app\models\User;
use app\validators\UserValidator;

class UserService
{
	private const DEFAULT_PER_PAGE = 15;
	private const MAX_PER_PAGE = 100;

	private UserValidator $validator;
	private bool $databaseEnabled;

	public function __construct(UserValidator $validator, bool $databaseEnabled = false)
	{
		$this->validator = $validator;
		$this->databaseEnabled = $databaseEnabled;
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
	 */
	public function list(array $filters = []): array
	{
		$this->ensureDatabaseIsReady();

		$page = $this->boundedInteger($filters['page'] ?? null, 1, PHP_INT_MAX, 1);
		$perPage = $this->boundedInteger($filters['per_page'] ?? null, 1, self::MAX_PER_PAGE, self::DEFAULT_PER_PAGE);
		$search = trim((string) ($filters['search'] ?? ''));

		$query = User::query();

		if ($search !== '') {
			$like = '%' . $this->escapeLike($search) . '%';
			$query->where(static function ($query) use ($like): void {
				$query->where('name', 'like', $like)
					->orWhere('email', 'like', $like);
			});
		}

		$total = (clone $query)->count();
		$items = $query
			->orderBy('id')
			->offset(($page - 1) * $perPage)
			->limit($perPage)
			->get()
			->map(static fn(User $user): array => $user->toArray())
			->all();

		return [
			'items' => $items,
			'meta' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $total,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function find(int $id): array
	{
		return $this->findModel($id)->toArray();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function create(array $data): array
	{
		$payload = $this->prepareUserPayload($this->validator->validateCreate($data));
		$this->ensureDatabaseIsReady();

		$user = User::query()->create($payload);

		return $user->toArray();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function update(int $id, array $data): array
	{
		$payload = $this->prepareUserPayload($this->validator->validateUpdate($data));
		$user = $this->findModel($id);

		$user->fill($payload);
		$user->save();

		return $user->fresh()->toArray();
	}

	public function delete(int $id): void
	{
		$user = $this->findModel($id);
		$user->delete();
	}

	private function findModel(int $id): User
	{
		$this->ensureDatabaseIsReady();

		$user = User::query()->find($id);
		if ($user === null) {
			throw new HttpException('Usuario no encontrado', 404);
		}

		return $user;
	}

	private function ensureDatabaseIsReady(): void
	{
		if ($this->databaseEnabled === false) {
			throw new DatabaseNotConfiguredException();
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function prepareUserPayload(array $data): array
	{
		if (isset($data['name'])) {
			$data['name'] = trim((string) $data['name']);
		}

		if (isset($data['email'])) {
			$data['email'] = strtolower(trim((string) $data['email']));
		}

		if (array_key_exists('password', $data)) {
			$password = trim((string) $data['password']);
			if ($password === '') {
				unset($data['password']);
			} else {
				$data['password'] = password_hash($password, PASSWORD_DEFAULT);
			}
		}

		return $data;
	}

	private function boundedInteger($value, int $min, int $max, int $default): int
	{
		$intValue = filter_var($value, FILTER_VALIDATE_INT);
		if ($intValue === false) {
			return $default;
		}

		return min(max($intValue, $min), $max);
	}

	private function escapeLike(string $value): string
	{
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}
}
