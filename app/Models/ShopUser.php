<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ShopUser
 * 
 * @property int $id
 * @property int $shop_id
 * @property int $user_id
 * 
 * @property Shop $shop
 * @property User $user
 *
 * @package App\Models
 */
class ShopUser extends Model
{
	protected $table = 'shop_users';
	public $timestamps = false;

	protected $casts = [
		'shop_id' => 'int',
		'user_id' => 'int'
	];

	protected $fillable = [
		'shop_id',
		'user_id'
	];

	public function shop()
	{
		return $this->belongsTo(Shop::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
