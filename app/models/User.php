<?php
declare(strict_types=1);

namespace app\models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
	protected $table = 'users';

	protected $fillable = [
		'name',
		'email',
		'password',
	];

	protected $hidden = [
		'password',
	];

	protected $casts = [
		'created_at' => 'datetime',
		'updated_at' => 'datetime',
	];
}
