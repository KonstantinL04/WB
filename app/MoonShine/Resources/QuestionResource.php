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

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Question>
 */
class QuestionResource extends ModelResource
{
    protected string $model = Question::class;

    protected string $title = 'Вопросы';

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Наименование товара', 'product.name')->sortable(),
            Text::make('Вопрос', 'question')->sortable(),
            Text::make('Статус', 'status')->sortable(),
        ];
    }

    protected function indexButtons(): ListOf
    {
        return parent::indexButtons()
            ->prepend(
                ActionButton::make('')
                    ->method('processReview')
                    ->icon('s.play')
            );
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
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
            Text::make('Статус', 'status'),
        ];
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
}
