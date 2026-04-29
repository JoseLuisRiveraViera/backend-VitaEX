<?php
declare(strict_types=1);

namespace app\models;

use app\config\Database;
use app\config\Env;
use InvalidArgumentException;
use PDO;
use RuntimeException;

abstract class BaseModel
{
	protected PDO $db;
	protected string $schema;

	public function __construct()
	{
		$this->db = Database::connection();
		$this->schema = Env::get('DB_SCHEMA', 'bolsa_trabajo') ?? 'bolsa_trabajo';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	protected function fetchAll(string $sql, array $params = []): array
	{
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function fetchOne(string $sql, array $params = []): ?array
	{
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$result = $stmt->fetch();
		return $result === false ? null : $result;
	}

	protected function execute(string $sql, array $params = []): int
	{
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->rowCount();
	}

	/**
	 * @return list<string>
	 */
	protected function tableColumns(string $table): array
	{
		$stmt = $this->db->prepare(
			'SELECT column_name
			FROM information_schema.columns
			WHERE table_schema = :schema AND table_name = :table
			ORDER BY ordinal_position'
		);
		$stmt->execute([
			'schema' => $this->schema,
			'table' => $table,
		]);

		return array_map(static fn(array $row): string => (string) $row['column_name'], $stmt->fetchAll());
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $blockedColumns
	 * @return array<string, mixed>
	 */
	protected function filterTableData(string $table, array $data, array $blockedColumns = []): array
	{
		$columns = array_diff($this->tableColumns($table), $blockedColumns);
		$allowed = array_flip($columns);

		return array_intersect_key($data, $allowed);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	protected function insert(string $table, array $data, string $primaryKey): array
	{
		if ($data === []) {
			throw new InvalidArgumentException('No hay campos válidos para insertar en ' . $table . '.');
		}

		$columns = array_keys($data);
		$columnSql = implode(', ', array_map([$this, 'identifier'], $columns));
		$paramSql = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s) RETURNING *',
			$this->identifier($table),
			$columnSql,
			$paramSql
		);

		$row = $this->fetchOne($sql, $data);
		if ($row === null) {
			throw new RuntimeException('No se pudo insertar el registro en ' . $table . '.');
		}

		return $row;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	protected function updateById(string $table, string $primaryKey, string|int $id, array $data): ?array
	{
		if ($data === []) {
			throw new InvalidArgumentException('No hay campos válidos para actualizar en ' . $table . '.');
		}

		$sets = [];
		foreach (array_keys($data) as $column) {
			$sets[] = $this->identifier($column) . ' = :' . $column;
		}

		$data[$primaryKey] = $id;
		$sql = sprintf(
			'UPDATE %s SET %s WHERE %s = :%s RETURNING *',
			$this->identifier($table),
			implode(', ', $sets),
			$this->identifier($primaryKey),
			$primaryKey
		);

		return $this->fetchOne($sql, $data);
	}

	protected function identifier(string $name): string
	{
		if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
			throw new InvalidArgumentException('Identificador SQL inválido: ' . $name);
		}

		return '"' . str_replace('"', '""', $name) . '"';
	}
}
