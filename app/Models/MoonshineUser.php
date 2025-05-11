<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
/**
 * Class MoonshineUser
 *
 * @property int $id
 * @property int|null $moonshine_user_role_id
 * @property string|null $email
 * @property string|null $password
 * @property string|null $name
 * @property string|null $avatar
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $seller_id
 * @property int|null $active_shop_id
 *
 * @property MoonshineUserRole|null $moonshine_user_role
 * @property Shop|null $shop
 * @property Collection|Shop[] $shops
 * @property Collection|Review[] $reviews
 * @property Collection|Question[] $questions
 *
 * @package App\Models
 */
class MoonshineUser extends Authenticatable
{
    use Notifiable;

	protected $table = 'moonshine_users';

	protected $casts = [
		'moonshine_user_role_id' => 'int',
		'seller_id' => 'int',
		'active_shop_id' => 'int'
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
		'remember_token',
		'seller_id',
		'active_shop_id'
	];

	public function moonshine_user_role()
	{
		return $this->belongsTo(MoonshineUserRole::class);
	}

	public function shop()
	{
		return $this->belongsTo(Shop::class, 'active_shop_id');
	}

	public function shops()
	{
		return $this->hasMany(Shop::class, 'user_id');
	}

	public function reviews()
	{
		return $this->hasMany(Review::class, 'user_id');
	}

	public function questions()
	{
		return $this->hasMany(Question::class, 'user_id');
	}
}
