<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Review;
use App\MoonShine\Handlers\ChatGPTHandler;
use App\MoonShine\Handlers\ChatGPTQuestionsHandler;
use App\MoonShine\Handlers\GetFeedBacks;
use App\MoonShine\Handlers\GetQuestions;
use Illuminate\Database\Eloquent\Model;
use App\Models\Question;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Question>
 */
class QuestionResource extends ModelResource
{
    protected string $model = Question::class;

    protected string $title = 'Вопросы';

    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::DELETE, Action::CREATE)
            // ->only(Action::VIEW)
            ;
    }
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            Text::make('Наименование товара', 'product.name')->sortable(),
            Text::make('Вопрос', 'question')->sortable(),
            Text::make('Тематики', 'questions_topic.name_topic')->sortable(),
            Text::make('Статус', 'status')->sortable(),
        ];
    }

    protected function indexButtons(): ListOf
    {
        return parent::indexButtons()
            ->prepend(
                ActionButton::make('')
                    ->method('publishAnswer')
                    ->icon('s.play')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите опубликовать ответ для данного отзыва?',
                        button: 'Опубликовать'
                    )
            );
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Preview::make('Наименование товара', 'product.name'),
                Text::make('Сформированный ответ', 'response'),
                Preview::make('Вопрос', 'question')->sortable(),
                Preview::make('Клиент', 'name_user'),
                Preview::make('Артикул', 'product.nm_id'),
                Preview::make('Определенная тематика', 'questions_topic.name_topic'),
                Preview::make('Статус', 'status'),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            Text::make('Наименование товара', 'product.name'),
            Text::make('Сформированный ответ', 'response'),
            Text::make('Вопрос', 'question')->sortable(),
            Text::make('Клиент', 'name_user'),
            Text::make('Артикул', 'product.nm_id'),
            Text::make('Определенная тематика', 'questions_topic.name_topic'),
            Text::make('Статус', 'status'),
        ];
    }

    protected function detailButtons(): ListOf
    {
        return parent::detailButtons()
            ->add(
                ActionButton::make('Опубликовать ответ')
                    ->method('publishAnswer')
                    ->icon('s.play')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите опубликовать ответ для данного отзыва?',
                        button: 'Опубликовать'
                    )
            );
    }

    /**
     * @param Review $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
    protected function handlers(): ListOf
    {
        return parent::handlers()
            ->add(new GetQuestions('Получить вопросы'))
            ->add(new ChatGPTQuestionsHandler('Сгенерировать ответы'));

    }

    public function publishAnswer(MoonShineRequest $request): MoonShineJsonResponse
    {
        /** @var Question $question */
        $question = $request->getResource()->getItem();

        $questionId = $question->question_id; // Убедитесь, что это поле есть в БД и заполнено
        $replyText = $question->response; // Ответ, который сгенерировала нейросеть или ввёл пользователь

        if (empty($questionId) || empty($replyText) || $question->status !== 'Сформирован') {
            return MoonShineJsonResponse::make()->toast('Нет ID вопроса или текста ответа', ToastType::ERROR);
        }
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.wildberries.token'),
            'Content-Type'  => 'application/json',
        ])->patch('https://feedbacks-api.wildberries.ru/api/v1/questions', [
            'id' => $questionId,
            'text' => $replyText,
            'wasViewed' => true,
        ]);

        if ($response->successful()) {
            $question->status = 'Опубликован';
            $question->save();

            return MoonShineJsonResponse::make()
                ->toast('Ответ на вопрос успешно опубликован', ToastType::SUCCESS);
        }

        Log::error('Ошибка при отправке ответа на вопрос', [
            'question_id' => $questionId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return MoonShineJsonResponse::make()
            ->toast('Ошибка при отправке ответа на вопрос', ToastType::ERROR);
    }
}
