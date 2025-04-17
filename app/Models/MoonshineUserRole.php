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
 * @property Collection|User[] $users
 * @property Collection|MoonshineUser[] $moonshine_users
 *
 * @package App\Models
 */
class MoonshineUserRole extends Model
{
	protected $table = 'moonshine_user_roles';

	protected $fillable = [
		'name'
	];

	public function users()
	{
		return $this->hasMany(User::class, 'role_id');
	}

	public function moonshine_users()
	{
		return $this->hasMany(MoonshineUser::class);
	}
}
