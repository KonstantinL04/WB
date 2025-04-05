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
 * 
 * @property User $user
 * @property Collection|User[] $users
 * @property Collection|Product[] $products
 *
 * @package App\Models
 */
class Shop extends Model
{
	protected $table = 'shops';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int'
	];

	protected $fillable = [
		'user_id',
		'name',
		'api_key'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function users()
	{
		return $this->belongsToMany(User::class, 'shop_users')
					->withPivot('id');
	}

	public function products()
	{
		return $this->hasMany(Product::class);
	}
}
