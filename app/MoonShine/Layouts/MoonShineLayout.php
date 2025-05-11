<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\MoonShineUserResource;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Components\Layout\{Locales, Notifications, Profile, Search};
use MoonShine\UI\Components\{Breadcrumbs,
    Components,
    Layout\Flash,
    Layout\Div,
    Layout\Body,
    Layout\Burger,
    Layout\Content,
    Layout\Footer,
    Layout\Head,
    Layout\Favicon,
    Layout\Assets,
    Layout\Meta,
    Layout\Header,
    Layout\Html,
    Layout\Layout,
    Layout\Logo,
    Layout\Menu,
    Layout\Sidebar,
    Layout\ThemeSwitcher,
    Layout\TopBar,
    Layout\Wrapper,
    When};
use App\MoonShine\Resources\PostResource;
use MoonShine\MenuManager\MenuItem;
use App\MoonShine\Resources\ReviewResource;
use App\MoonShine\Resources\ShopResource;
use App\MoonShine\Resources\UserResource;
use App\MoonShine\Resources\QuestionResource;
use App\MoonShine\Resources\ProductResource;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            MenuItem::make('Отзывы', ReviewResource::class)
             ->icon('chat-bubble-bottom-center-text'),
            MenuItem::make('Вопросы', QuestionResource::class)
            ->icon('question-mark-circle'),
            MenuItem::make('Товары', ProductResource::class)
                ->icon('rectangle-stack'),
            MenuItem::make('Магазины', ShopResource::class)
            ->icon('building-storefront')
                ->canSee(fn() => in_array(
                    auth()->user()->moonshine_user_role->name,
                    ['Админ', 'Продавец'],
                    true
                )),
            MenuItem::make('Пользователи', MoonShineUserResource::class)
                ->icon('users')
                ->canSee(fn() => in_array(
                    auth()->user()->moonshine_user_role->name,
                    ['Админ', 'Продавец'],
                    true
                )),

        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);
//        $colorManager->background('#00000');
        // $colorManager->primary('#00000');
    }

    public function build(): Layout
    {
        return parent::build();
    }
}
