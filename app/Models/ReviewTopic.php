<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ReviewTopic
 * 
 * @property int $id
 * @property string $name_topic
 * 
 * @property Collection|Review[] $reviews
 *
 * @package App\Models
 */
class ReviewTopic extends Model
{
	protected $table = 'review_topics';
	public $timestamps = false;

	protected $fillable = [
		'name_topic'
	];

	public function reviews()
	{
		return $this->hasMany(Review::class, 'topic_review_id');
	}
}
