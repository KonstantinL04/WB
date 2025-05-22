<?php

namespace App\Providers;

use App\Models\MoonshineUser;
use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\Shop;
use App\MoonShine\Resources\MoonShineUserResource;
use App\Policies\MoonShineUserPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ShopPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Маппинг моделей к политикам.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Shop::class => ShopPolicy::class,
        Review::class => ReviewPolicy::class,
        Question::class => QuestionPolicy::class,
        MoonShineUser::class => MoonShineUserPolicy::class,
        Product::class => ProductPolicy::class,
        // ... другие политики
    ];

    /**
     * Регистрация любых аутентификационных / авторизационных сервисов.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
