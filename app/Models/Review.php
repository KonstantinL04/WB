<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Review
 * 
 * @property int $id
 * @property string $review_id
 * @property int $product_id
 * @property int|null $evaluation
 * @property string|null $name_user
 * @property string|null $photos
 * @property string|null $videos
 * @property string|null $sentiment
 * @property int|null $topic_review_id
 * @property string|null $pluses
 * @property string|null $cons
 * @property string|null $comment_text
 * @property string|null $response
 * @property string|null $status
 * @property Carbon|null $created_date
 * @property ARRAY|null $published_date
 * @property int|null $user_id
 * 
 * @property Product $product
 * @property ReviewTopic|null $review_topic
 * @property User|null $user
 *
 * @package App\Models
 */
class Review extends Model
{
	protected $table = 'reviews';
	public $timestamps = false;

	protected $casts = [
		'product_id' => 'int',
		'evaluation' => 'int',
		'topic_review_id' => 'int',
		'created_date' => 'datetime',
		'published_date' => 'ARRAY',
		'user_id' => 'int'
	];

	protected $fillable = [
		'review_id',
		'product_id',
		'evaluation',
		'name_user',
		'photos',
		'videos',
		'sentiment',
		'topic_review_id',
		'pluses',
		'cons',
		'comment_text',
		'response',
		'status',
		'created_date',
		'published_date',
		'user_id'
	];

	public function product()
	{
		return $this->belongsTo(Product::class);
	}

	public function review_topic()
	{
		return $this->belongsTo(ReviewTopic::class, 'topic_review_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
