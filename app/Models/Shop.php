<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Shop
 * 
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $api_key
 * @property bool|null $is_active
 * 
 * @property MoonshineUser $moonshine_user
 * @property Collection|MoonshineUser[] $moonshine_users
 * @property Collection|Product[] $products
 *
 * @package App\Models
 */
class Shop extends Model
{
	protected $table = 'shops';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'is_active' => 'bool'
	];

	protected $fillable = [
		'user_id',
		'name',
		'api_key',
		'is_active'
	];

	public function moonshine_user()
	{
		return $this->belongsTo(MoonshineUser::class, 'user_id');
	}

	public function moonshine_users()
	{
		return $this->hasMany(MoonshineUser::class, 'active_shop_id');
	}

	public function products()
	{
		return $this->hasMany(Product::class);
	}
}
