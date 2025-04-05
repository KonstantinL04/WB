<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

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
 * 
 * @property Product $product
 * @property QuestionsTopic|null $questions_topic
 *
 * @package App\Models
 */
class Question extends Model
{
	protected $table = 'questions';
	public $timestamps = false;

	protected $casts = [
		'product_id' => 'int',
		'topic_review_id' => 'int'
	];

	protected $fillable = [
		'question_id',
		'product_id',
		'name_user',
		'question',
		'sentiment',
		'topic_review_id',
		'response',
		'status'
	];

	public function product()
	{
		return $this->belongsTo(Product::class);
	}

	public function questions_topic()
	{
		return $this->belongsTo(QuestionsTopic::class, 'topic_review_id');
	}
}
