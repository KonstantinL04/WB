<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

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
 *
 * @property Product $product
 * @property ReviewTopic|null $review_topic
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
        'photos' => 'array',
        'videos' => 'array',
        'created_date' => 'datetime',
        'published_date' => 'datetime',
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
	];

	public function product()
	{
		return $this->belongsTo(Product::class, 'product_id');
	}

	public function review_topic()
	{
		return $this->belongsTo(ReviewTopic::class, 'topic_review_id');
	}
}
