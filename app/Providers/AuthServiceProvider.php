<?php

namespace App\Providers;

use App\Models\MoonshineUser;
use App\Models\Question;
use App\Models\Review;
use App\Models\Shop;
use App\MoonShine\Resources\MoonShineUserResource;
use App\Policies\MoonShineUserPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ShopPolicy;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    /**
     * Маппинг моделей к политикам.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Shop::class => ShopPolicy::class,
        Review::class => ReviewPolicy::class,
        Question::class => QuestionPolicy::class,
        MoonShineUser::class => MoonShineUserPolicy::class,

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
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
