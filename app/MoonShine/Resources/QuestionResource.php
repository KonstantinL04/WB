<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Review;
use App\MoonShine\Handlers\ChatGPTReviewHandler;
use App\MoonShine\Handlers\ChatGPTQuestionsHandler;
use App\MoonShine\Handlers\GetFeedBacks;
use App\MoonShine\Handlers\GetQuestions;
use App\MoonShine\Handlers\PublishResponseQuestions;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Question;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends ModelResource<Question>
 */
class QuestionResource extends ModelResource
{
    protected string $model = Question::class;

    protected string $title = 'Вопросы';
    protected SortDirection $sortDirection = SortDirection::ASC;


    protected function activeActions(): ListOf
    {
        return parent::activeActions()
            ->except(Action::DELETE, Action::CREATE)
            // ->only(Action::VIEW)
            ;
    }

    protected function filters(): iterable
    {
        return [
            Select::make('Тип', 'sentiment')
                ->options(static function () {
                    // Извлекаем уникальные значения поля sentiment из таблицы reviews
                    return \App\Models\Question::query()
                        ->select('sentiment')
                        ->distinct()
                        ->pluck('sentiment', 'sentiment')
                        ->toArray();
                })
                ->multiple()
                ->nullable()
                ->onApply(static function (Builder $query, mixed $value, Select $field) {
                    // Если значение не пустое, фильтруем по полю sentiment
                    if (!empty($value)) {
                        $query->whereIn('sentiment', (array) $value);
                    }
                }),
            Select::make('Тематика', 'topic_review_id')
                ->options(static function () {
                    // Извлекаем список всех тематик из таблицы вопросов тем
                    // Предполагается, что модель QuestionsTopic содержит поля id и name_topic
                    return \App\Models\QuestionsTopic::query()
                        ->select('name_topic', 'id')
                        ->pluck('name_topic', 'id')
                        ->toArray();
                })
                ->multiple()
                ->nullable()
                ->onApply(static function (Builder $query, mixed $value, Select $field) {
                    if (!empty($value)) {
                        $query->whereIn('topic_review_id', (array)$value);
                    }
                }),
            Select::make('Статус', 'status')
                ->options(static function () {
                    // Извлекаем уникальные значения поля sentiment из таблицы reviews
                    return \App\Models\Question::query()
                        ->select('status')
                        ->distinct()
                        ->pluck('status', 'status')
                        ->toArray();
                })
                ->multiple()
                ->nullable()
                ->onApply(static function (Builder $query, mixed $value, Select $field) {
                    // Если значение не пустое, фильтруем по полю sentiment
                    if (!empty($value)) {
                        $query->whereIn('status', (array) $value);
                    }
                }),
        ];
    }
    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            Date::make('Дата вопроса', 'created_date')->sortable()
                ->badge()
                ->format('d.m.Y'),
            Text::make('Наименование товара', 'product.name')
                ->sortable(function ($query, string $direction) {
                    // Гарантируем, что направление сортировки будет либо "asc", либо "desc":
                    $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    // Используем leftJoin для корректного выбора и сортировки:
                    $query->leftJoin('products', 'products.id', '=', 'questions.product_id')
                        ->orderBy('products.name', $direction)
                        ->select('questions.*');
                }),
//            Text::make('Вопрос', 'question')->sortable(),

            Preview::make('Тематика', 'questions_topic.name_topic')
                ->sortable(function ($query, string $direction) {
                    // Гарантируем, что направление сортировки будет либо "asc", либо "desc":
                    $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    // Используем leftJoin для корректного выбора и сортировки:
                    $query->leftJoin('questions_topics', 'questions_topics.id', '=', 'questions.topic_review_id')
                        ->orderBy('questions_topics.name_topic', $direction)
                        ->select('questions.*');
                })
                ->badge('purple'),
//            Preview::make('Тема', 'questions_topic.name_topic')->sortable()
//                ->badge('purple'),
            Preview::make('Тип', 'sentiment')
                ->badge(fn($sentiment) => match ($sentiment) {
                    'Типовой' => 'green',
                    'Нетиповой' => 'red',
                    default => 'gray',
                })
                ->sortable(),
            Preview::make('Статус', 'status')
                ->badge(fn($status) => match ($status) {
                    'Сформирован' => 'yellow',
                    'Новый' => 'purple',
                    'Опубликован' => 'green',
                    default => 'gray',
                })
                ->sortable(),
        ];
    }

    protected function indexButtons(): ListOf
    {
        return parent::indexButtons()
            ->prepend(
                ActionButton::make('')
                    ->canSee(fn($item) => $item->status === 'Новый')
                    ->method('chatGPTHandlerQuestion') // вызываем нужный обработчик, например ChatGPTReviewHandler
                    ->icon('s.play')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите сгенерировать ответ для данного отзыва?',
                        button: 'Сгенерировать'
                    )
            )
            ->prepend(
                ActionButton::make('')
                    ->canSee(fn($item) => $item->status === 'Сформирован')
                    ->method('publishAnswer')
                    ->icon('s.arrow-up-circle')
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
                Column::make([
                    Preview::make('Наименование товара', 'product.name'),
                    Preview::make('Вопрос', 'question')->sortable()
                        ->badge('yellow'),
                    Textarea::make('Сформированный ответ', 'response'),
                    Preview::make('Клиент', 'name_user'),
                    Preview::make('Артикул', 'product.nm_id'),
                    Preview::make('Тематика', 'questions_topic.name_topic')
                        ->badge('purple'),
                    Date::make('Дата вопроса', 'created_date')->sortable()
                        ->badge()
                        ->format('d.m.Y')
                        ->readonly(),
                    Preview::make('Статус', 'status')
                        ->badge(fn($status) => match ($status) {
                            'Сформирован' => 'yellow',
                            'Новый' => 'purple',
                            'Опубликован' => 'green',
                            default => 'gray',
                        })
                        ->sortable(),
                ])->columnSpan(8)
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            Preview::make('Тип', 'sentiment')
                ->badge(fn($sentiment) => match ($sentiment) {
                    'Типовой' => 'green',
                    'Нетиповой' => 'red',
                    default => 'gray',
                })
                ->sortable(),

            Text::make('Наименование товара', 'product.name'),
            Text::make('Вопрос', 'question')->sortable()
                ->badge('yellow'),
            Text::make('Сформированный ответ', 'response'),

            Text::make('Клиент', 'name_user'),
            Text::make('Артикул', 'product.nm_id'),
            Text::make('Тематика', 'questions_topic.name_topic')
                ->badge('purple'),
            Preview::make('Статус', 'status')
                ->badge(fn($status) => match ($status) {
                    'Сформирован' => 'yellow',
                    'Новый' => 'purple',
                    'Опубликован' => 'green',
                    default => 'gray',
                })
                ->sortable(),
        ];
    }

    protected function detailButtons(): ListOf
    {
        return parent::detailButtons()
            ->add(
                ActionButton::make('Сгенерировать ответ')
                    ->method('chatGPTHandlerQuestion')
                    ->icon('s.play')
                    ->canSee(fn($item) => $item->status === 'Новый')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите сгенерировать ответ для данного отзыва?',
                        button: 'Сгенерировать'
                    )
            )
            ->add(
                ActionButton::make('Опубликовать ответ')
                    ->method('publishAnswer')
                    ->icon('s.arrow-up-circle')
                    ->canSee(fn($item) => $item->status === 'Сформирован')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите опубликовать ответ для данного отзыва?',
                        button: 'Опубликовать'
                    )
            );
    }

    /**
     * @param Question $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
//    protected function handlers(): ListOf
//    {
//        return parent::handlers()
//            ->add(new GetQuestions('Получить вопросы'))
//            ->add(new ChatGPTQuestionsHandler('Сгенерировать ответы'));
//
//    }

    protected function topButtons(): ListOf
    {
        return parent::topButtons()
            ->add(
                ActionButton::make('Получить вопросы')
                    ->method('getQuestions')
                    ->icon('s.arrow-down-circle')
                    ->primary()
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите получить новые вопросы?',
                        button: 'Получить'
                    )
            )
            ->add(
                ActionButton::make('Сгенерировать ответы')
                    ->method('getChatGPT')
                    ->icon('s.play')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Вы действительно хотите сгенерировать ответы для всех вопросов?',
                        button: 'Сгенерировать'
                    )
            )
            ->add(
                ActionButton::make('', '#')
                    ->dispatchEvent(AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName()))
                    ->icon('s.arrow-path')
            );
    }

    public function getQuestions(MoonShineRequest $request): Response
    {
        // Создаём экземпляр класса GetFeedBacks и вызываем его метод handle()
        $handler = new GetQuestions('Импортировать вопросы');
        return $handler->handle();
    }

    public function getChatGPT(MoonShineRequest $request): Response
    {
        // Создаём экземпляр класса GetFeedBacks и вызываем его метод handle()
        $handler = new ChatGPTQuestionsHandler('Начать формирование');
        return $handler->handle();
    }

    public function chatGPTHandlerQuestion(MoonShineRequest $request): MoonShineJsonResponse
    {
        /** @var Question $questionItem */
        $questionItem = $request->getResource()->getItem();

        if (!$questionItem) {
            return MoonShineJsonResponse::make()->toast('Отзыв не найден', ToastType::ERROR);
        }

        ChatGPTQuestionsHandler::processQuestion($questionItem);

        return MoonShineJsonResponse::make()->toast('Ответ успешно сгенерирован', ToastType::SUCCESS);
    }

    public function publishAnswer(MoonShineRequest $request)
    {
        $handler = new PublishResponseQuestions('Опубликовать ответ');
        return $handler->handle();
    }
}
