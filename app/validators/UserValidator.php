<?php
declare(strict_types=1);

namespace app\validators;

use app\exceptions\ValidationException;
use Rakit\Validation\Validator;

class UserValidator
{
	private Validator $validator;

	public function __construct(Validator $validator)
	{
		$this->validator = $validator;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function validateCreate(array $data): array
	{
		return $this->validate($data, [
			'name' => 'required|min:2|max:120',
			'email' => 'required|email|max:180',
			'password' => 'nullable|min:8|max:255',
		]);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function validateUpdate(array $data): array
	{
		if ($data === []) {
			throw new ValidationException([
				'body' => 'Debes enviar al menos un campo para actualizar',
			]);
		}

		return $this->validate($data, [
			'name' => 'nullable|min:2|max:120',
			'email' => 'nullable|email|max:180',
			'password' => 'nullable|min:8|max:255',
		]);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, string> $rules
	 * @return array<string, mixed>
	 */
	private function validate(array $data, array $rules): array
	{
		$validation = $this->validator->make($data, $rules);
		$validation->setAliases([
			'name' => 'nombre',
			'email' => 'email',
			'password' => 'contrasena',
		]);
		$validation->setMessages([
			'required' => ':attribute es obligatorio',
			'min' => ':attribute debe tener al menos :min caracteres',
			'max' => ':attribute no debe ser mayor a :max caracteres',
		]);
		$validation->validate();

		if ($validation->fails()) {
			throw new ValidationException($this->firstErrors($validation->errors()->toArray()));
		}

		return array_intersect_key(
			$validation->getValidData(),
			array_flip(array_keys($rules))
		);
	}

	/**
	 * @param array<string, array<string, string>> $errors
	 * @return array<string, string>
	 */
	private function firstErrors(array $errors): array
	{
		$formatted = [];

		foreach ($errors as $field => $messages) {
			$rule = array_key_first($messages);
			$formatted[$field] = $rule === 'email'
				? 'email debe tener formato valido'
				: (string) reset($messages);
		}

		return $formatted;
	}
}
