<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MoonshineUserRole
 * 
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|MoonshineUser[] $moonshine_users
 * @property Collection|User[] $users
 *
 * @package App\Models
 */
class MoonshineUserRole extends Model
{
	protected $table = 'moonshine_user_roles';

	protected $fillable = [
		'name'
	];

	public function moonshine_users()
	{
		return $this->hasMany(MoonshineUser::class);
	}

	public function users()
	{
		return $this->hasMany(User::class, 'role_id');
	}
}
