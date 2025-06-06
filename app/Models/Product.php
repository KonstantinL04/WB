<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Product
 *
 * @property int $id
 * @property int $shop_id
 * @property int $nm_id
 * @property string $name
 * @property string|null $category
 * @property string|null $characteristics
 * @property string|null $description
 * @property string|null $color
 * @property string|null $gender
 * @property string|null $country_manufacture
 * @property string|null $image
 *
 * @property Shop $shop
 * @property Collection|Review[] $reviews
 * @property Collection|Question[] $questions
 *
 * @package App\Models
 */
class Product extends Model
{
	protected $table = 'products';
	public $timestamps = false;

	protected $casts = [
		'shop_id' => 'int',
		'nm_id' => 'int',
		'characteristics' => 'json',
	];

	protected $fillable = [
		'shop_id',
		'nm_id',
		'name',
		'category',
		'characteristics',
		'description',
		'color',
		'country_manufacture',
		'image',
        'range_evaluation'
	];

	public function shop()
	{
		return $this->belongsTo(Shop::class);
	}

	public function reviews()
	{
		return $this->hasMany(Review::class);
	}

	public function questions()
	{
		return $this->hasMany(Question::class);
	}
}
