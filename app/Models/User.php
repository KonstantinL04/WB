<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $role_id
 * 
 * @property MoonshineUserRole|null $moonshine_user_role
 * @property Collection|Shop[] $shops
 * @property Collection|Review[] $reviews
 *
 * @package App\Models
 */
class User extends Model
{
	protected $table = 'users';

	protected $casts = [
		'email_verified_at' => 'datetime',
		'role_id' => 'int'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'name',
		'email',
		'email_verified_at',
		'password',
		'remember_token',
		'role_id'
	];

	public function moonshine_user_role()
	{
		return $this->belongsTo(MoonshineUserRole::class, 'role_id');
	}

	public function shops()
	{
		return $this->belongsToMany(Shop::class, 'shop_users')
					->withPivot('id');
	}

	public function reviews()
	{
		return $this->hasMany(Review::class);
	}
}
