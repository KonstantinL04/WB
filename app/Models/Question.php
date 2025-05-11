<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Question
 *
 * @property int $id
 * @property string $question_id
 * @property int $product_id
 * @property string|null $name_user
 * @property string|null $question
 * @property string|null $sentiment
 * @property int|null $topic_review_id
 * @property string|null $response
 * @property string|null $status
 * @property Carbon|null $created_date
 * @property Carbon|null $published_date
 * @property int|null $user_id
 *
 * @property Product $product
 * @property QuestionsTopic|null $questions_topic
 * @property MoonshineUser|null $moonshine_user
 *
 * @package App\Models
 */
class Question extends Model
{
	protected $table = 'questions';
	public $timestamps = false;

	protected $casts = [
		'product_id' => 'int',
		'topic_review_id' => 'int',
		'created_date' => 'datetime',
		'published_date' => 'datetime',
		'user_id' => 'int'
	];

	protected $fillable = [
		'question_id',
		'product_id',
		'name_user',
		'question',
		'sentiment',
		'topic_review_id',
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

	public function questions_topic()
	{
		return $this->belongsTo(QuestionsTopic::class, 'topic_review_id');
	}

	public function moonshine_user()
	{
		return $this->belongsTo(MoonshineUser::class, 'user_id');
	}
}
