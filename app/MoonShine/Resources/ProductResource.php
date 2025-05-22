<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Shop;
use App\MoonShine\Pages\ProductDetailPage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/**
 * @extends ModelResource<Product>
 */
class ProductResource extends ModelResource
{
    protected string $model = Product::class;

    protected string $title = 'Товары';
    protected bool $columnSelection = true;
    protected bool $withPolicy = true;

    protected function filters(): iterable{
        return [
            Select::make('Категория', 'category')
                ->options(static function () {
                    // Извлекаем уникальные значения поля sentiment из таблицы reviews
                    return Product::query()
                        ->select('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray();
                })
                ->multiple()
                ->nullable()
                ->onApply(static function (Builder $query, mixed $value, Select $field) {
                    // Если значение не пустое, фильтруем по полю sentiment
                    if (!empty($value)) {
                        $query->whereIn('category', (array) $value);
                    }
                }),

            Select::make('Цвет', 'color')
                ->options(static function () {
                    // Извлекаем уникальные значения поля sentiment из таблицы reviews
                    return Product::query()
                        ->select('color')
                        ->distinct()
                        ->pluck('color', 'color')
                        ->toArray();
                })
                ->multiple()
                ->nullable()
                ->onApply(static function (Builder $query, mixed $value, Select $field) {
                    // Если значение не пустое, фильтруем по полю sentiment
                    if (!empty($value)) {
                        $query->whereIn('color', (array) $value);
                    }
                }),
        ];
    }
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

        // Фильтруем отзывы по товарам этого магазина
        return $builder->where('shop_id', $shopId);
    }

    /**
     * @return list<FieldContract>
     */

    protected function indexFields(): iterable
    {
        return [
            Url::make('Фото', 'image')
                // В качестве «текста» ссылки выводим тег <img>
                ->title(fn(string $url) => "<img src='{$url}' style='max-width:40px;' alt=''>")
                // ссылка откроется в новой вкладке
                ->blank(),
            Text::make('Средняя оценка', 'range_evaluation'),
            Text::make('Наименование', 'name')->sortable(),
            Text::make('Цвет', 'color')->sortable(),
            Text::make('Категория', 'category')->sortable(),

        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Url::make('Фото', 'image')
                    // В качестве «текста» ссылки выводим тег <img>
                    ->title(fn(string $url) => "<img src='{$url}' style='max-width:40px;' alt=''>")
                    // ссылка откроется в новой вкладке
                    ->blank(),
                Text::make('Наименование', 'name')->sortable(),
                Text::make('Цвет', 'color')->sortable(),
                Text::make('Категория', 'category')->sortable(),
                Text::make('Страна производства', 'country_manufacture')->sortable(),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            Text::make('Наименование', 'name')->sortable(),
            Text::make('Цвет', 'color')->sortable(),
            Text::make('Категория', 'category')->sortable(),
            Textarea::make('Описание', 'description')->sortable(),
            Text::make('Страна производства', 'country_manufacture')->sortable(),

            Url::make('Фото', 'image')
                // В качестве «текста» ссылки выводим тег <img>
                ->title(fn(string $url) => "<img src='{$url}' style='max-width:40px;' alt=''>")
                // ссылка откроется в новой вкладке
                ->blank(),
        ];
    }
    protected function search(): array
    {
        return [
            'id',
            'name',
            'category',
            'color',
        ];
    }

    /**
     * @param Product $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
