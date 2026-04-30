<?php
declare(strict_types=1);

namespace app\core;

class Validator
{
	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $fields
	 * @return array<string, string>
	 */
	public static function required(array $data, array $fields): array
	{
		$errors = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $data) === false || $data[$field] === null || $data[$field] === '') {
				$errors[$field] = 'El campo ' . $field . ' es requerido.';
			}
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $fields
	 * @return array<string, string>
	 */
	public static function notBlankWhenPresent(array $data, array $fields): array
	{
		$errors = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $data) && is_string($data[$field]) && trim($data[$field]) === '') {
				$errors[$field] = 'El campo ' . $field . ' no puede estar vacío.';
			}
		}

		return $errors;
	}

	public static function email($value): bool
	{
		return $value === null || $value === '' || filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false;
	}

	public static function in($value, array $allowed): bool
	{
		return in_array($value, $allowed, true);
	}

	public static function numericRange($value, float|int $min, float|int $max): bool
	{
		return is_numeric($value) && (float) $value >= $min && (float) $value <= $max;
	}

	public static function maxLength($value, int $length): bool
	{
		return $value === null || mb_strlen((string) $value) <= $length;
	}

	public static function isPositiveNumber($value): bool
	{
		return is_numeric($value) && (float) $value >= 0;
	}

	/**
	 * @param array<string,mixed> $query
	 * @return array{page:int,limit:int,offset:int}
	 */
	public static function validatePagination(array $query): array
	{
		$page = max(1, (int) ($query['page'] ?? 1));
		$limit = max(1, min(100, (int) ($query['limit'] ?? 10)));

		return [
			'page' => $page,
			'limit' => $limit,
			'offset' => ($page - 1) * $limit,
		];
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,string>
	 */
	public static function validate(array $data, array $rules): array
	{
		$errors = [];

		foreach ($rules as $field => $fieldRules) {
			foreach ($fieldRules as $rule => $option) {
				$value = $data[$field] ?? null;
				if ($rule === 'required' && ($value === null || $value === '')) {
					$errors[$field] = 'El campo ' . $field . ' es requerido.';
				}
				if ($rule === 'email' && self::email($value) === false) {
					$errors[$field] = 'El campo ' . $field . ' debe ser un correo válido.';
				}
				if ($rule === 'in' && $value !== null && self::in($value, $option) === false) {
					$errors[$field] = 'El campo ' . $field . ' no tiene un valor permitido.';
				}
				if ($rule === 'max' && self::maxLength($value, (int) $option) === false) {
					$errors[$field] = 'El campo ' . $field . ' excede la longitud máxima.';
				}
			}
		}

		return $errors;
	}
}
