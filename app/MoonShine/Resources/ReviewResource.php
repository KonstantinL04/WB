<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;
use App\MoonShine\Handlers\ChatGPTHandler;
use App\MoonShine\Handlers\GetFeedBacks;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Review>
 */
class ReviewResource extends ModelResource
{
    protected string $model = Review::class;

    protected string $title = 'Отзывы';
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
            Select::make('Тональность', 'sentiment')
                ->options(static function () {
                    // Извлекаем уникальные значения поля sentiment из таблицы reviews
                    return \App\Models\Review::query()
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

            Select::make('Статус', 'status')
                ->options(static function () {
                    // Извлекаем уникальные значения поля sentiment из таблицы reviews
                    return \App\Models\Review::query()
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
            Date::make('Дата отзыва', 'created_date')->sortable()
                ->format('d.m.Y'),
            Text::make('Наименование товара', 'product.name')
                ->sortable(function ($query, string $direction) {
                    // Гарантируем, что направление сортировки будет либо "asc", либо "desc":
                    $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    // Используем leftJoin для корректного выбора и сортировки:
                    $query->leftJoin('products', 'products.id', '=', 'reviews.product_id')
                        ->orderBy('products.name', $direction)
                        ->select('reviews.*');
                }),
            Number::make('Оценка', 'evaluation')->sortable()
                ->stars()
                ->min(1)
                ->max(5),
            Preview::make('Тональность', 'sentiment')
                ->badge(fn($sentiment) => match ($sentiment) {
                    'положительная' => 'green',
                    'нейтральная' => 'yellow',
                    'отрицательная' => 'red',
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
                    ->method('chatGPTHandler') // вызываем нужный обработчик, например ChatGPTHandler
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
     * @throws \Throwable
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Text::make('Наименование товара', 'product.name')
                    ->locked(),
                Text::make('Определенная тематика', 'review_topic.name_topic')
                    ->locked(),
                Text::make('Сформированный ответ', 'response'),
                Number::make('Оценка', 'evaluation')
                    ->stars()
                    ->min(1)
                    ->max(5)
                    ->locked(),
                Text::make('~ Комментарий', 'comment_text')
                    ->locked(),
                Text::make('+ Достоинства', 'pluses')
                    ->locked(),
                Text::make('- Недостатки', 'cons')
                    ->locked(),
                Text::make('Клиент', 'name_user')
                    ->locked(),
//            Text::make('Фото', 'photos'),
//            Text::make('Видео', 'videos'),
                Text::make('Артикул', 'product.nm_id')
                    ->locked(),
                Text::make('Статус', 'status')
                    ->locked(),
                Date::make('Дата оставления', 'created_date')->sortable()
                    ->format('d.m.Y')
                    ->locked(),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     * @throws \Throwable
     */
    protected function detailFields(): iterable
    {
        return [
            Text::make('Наименование товара', 'product.name'),
            Text::make('Определенная тематика', 'review_topic.name_topic'),
            Text::make('Сформированный ответ', 'response'),
            Number::make('Оценка', 'evaluation')
                ->stars()
                ->min(1)
                ->max(5)
                ->step(1),
            Text::make('~ Комментарий', 'comment_text'),
            Text::make('+ Достоинства', 'pluses'),
            Text::make('- Недостатки', 'cons'),
            Text::make('Клиент', 'name_user'),
//            Text::make('Фото', 'photos'),
//            Text::make('Видео', 'videos'),
            Text::make('Артикул', 'product.nm_id'),
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
            ->add(new GetFeedBacks('Получить отзывы'))
            ->add(new ChatGPTHandler('Сгенерировать ответы'));

    }

    public function chatGPTHandler(MoonShineRequest $request): MoonShineJsonResponse
    {
        /** @var Review $review */
        $review = $request->getResource()->getItem();

        if (!$review) {
            return MoonShineJsonResponse::make()->toast('Отзыв не найден', ToastType::ERROR);
        }

        ChatGPTHandler::processReview($review);

        return MoonShineJsonResponse::make()->toast('Ответ успешно сгенерирован', ToastType::SUCCESS);
    }
    public function publishAnswer(MoonShineRequest $request): MoonShineJsonResponse
    {
        /** @var Review $review */
        $review = $request->getResource()->getItem();

        $reviewId = $review->review_id; // Убедитесь, что это поле есть в БД и заполнено
        $replyText = $review->response; // Ответ, который сгенерировала нейросеть или ввёл пользователь

        if (empty($reviewId) || empty($replyText)) {
            return MoonShineJsonResponse::make()->toast('Нет ID отзыва или текста ответа', ToastType::ERROR);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.wildberries.token'),
            'Content-Type' => 'application/json',
        ])->post('https://feedbacks-api.wildberries.ru/api/v1/feedbacks/answer', [
            'id' => $reviewId,
            'text' => $replyText,
        ]);

        if ($response->successful()) {
            $review->status = 'Опубликован';
            $review->save();

            return MoonShineJsonResponse::make()->toast('Ответ опубликован успешно!', ToastType::SUCCESS);
        }

        Log::error('Ошибка при публикации ответа на WB', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return MoonShineJsonResponse::make()->toast('Ошибка при отправке ответа', ToastType::ERROR);
    }
}
