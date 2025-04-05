<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class QuestionsTopic
 * 
 * @property int $id
 * @property string $name_topic
 * 
 * @property Collection|Question[] $questions
 *
 * @package App\Models
 */
class QuestionsTopic extends Model
{
	protected $table = 'questions_topics';
	public $timestamps = false;

	protected $fillable = [
		'name_topic'
	];

	public function questions()
	{
		return $this->hasMany(Question::class, 'topic_review_id');
	}
}
