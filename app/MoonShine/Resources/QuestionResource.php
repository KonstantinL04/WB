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
use MoonShine\UI\Fields\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends ModelResource<Question>
 */
class QuestionResource extends ModelResource
{
    protected string $model = Question::class;
    protected bool $columnSelection = true;


    protected string $title = 'Вопросы';
    protected bool $withPolicy = true;

    protected SortDirection $sortDirection = SortDirection::ASC;


    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        // Админ видит всё
        if (auth()->user()->moonshine_user_role->name === 'Админ') {
            return $builder;
        }

        $user = auth()->user();

        // Определяем, откуда взять ID активного магазина
        $shopId = $user->active_shop_id
            ?? $user->shops()->where('is_active', true)->value('id');

        // Фильтруем вопросы по товарам этого магазина
        return $builder->whereHas('product', fn($q) => $q->where('shop_id', $shopId));
    }
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
            Textarea::make('Вопрос', 'question')->sortable(),
            Url::make('Фото', 'product.image')
                // В качестве «текста» ссылки выводим тег <img>
                ->title(fn(string $url) => "<img src='{$url}' style='max-width:40px;' alt=''>")
                // ссылка откроется в новой вкладке
                ->blank(),
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
                    Preview::make('Тип', 'sentiment')
                        ->badge(fn($sentiment) => match ($sentiment) {
                            'Типовой' => 'green',
                            'Нетиповой' => 'red',
                            default => 'gray',
                        })
                        ->sortable(),
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
            Date::make('Дата вопроса', 'created_date')->sortable()
                ->badge()
                ->format('d.m.Y'),
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
                    ->canSee(fn() => Question::count() === 0)
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
