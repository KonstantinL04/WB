<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\PageType;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Text;


class ProductDetailPage extends DetailPage
{
    protected PageType $type = PageType::DETAIL;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle()
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'ProductDetailPage';
    }

    /**
     * @return list<ComponentContract>
     */
    public function fields(): array
    {
        return [
            Text::make('Наименование', 'name'),
            Text::make('Цвет', 'color'),
            Text::make('Страна производства', 'country_manufacture'),
        ];
    }

    protected function components(): iterable
    {
        return [
            Tabs::make([
                Tab::make('Основное', $this->fields())
                    ->icon('academic-cap'),
                Tab::make('Статистика', [
                ])->icon('academic-cap'),
            ]),
        ];
    }
}
