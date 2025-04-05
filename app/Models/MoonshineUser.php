<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MoonshineUser
 * 
 * @property int $id
 * @property int $moonshine_user_role_id
 * @property string $email
 * @property string $password
 * @property string $name
 * @property string|null $avatar
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property MoonshineUserRole $moonshine_user_role
 *
 * @package App\Models
 */
class MoonshineUser extends Model
{
	protected $table = 'moonshine_users';

	protected $casts = [
		'moonshine_user_role_id' => 'int'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'moonshine_user_role_id',
		'email',
		'password',
		'name',
		'avatar',
		'remember_token'
	];

	public function moonshine_user_role()
	{
		return $this->belongsTo(MoonshineUserRole::class);
	}
}
