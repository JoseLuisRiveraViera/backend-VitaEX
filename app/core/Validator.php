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
}
