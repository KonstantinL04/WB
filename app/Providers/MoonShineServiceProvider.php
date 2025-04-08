<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use App\MoonShine\Resources\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRoleResource;
use App\MoonShine\Resources\PostResource;
use App\MoonShine\Resources\ReviewResource;
use App\MoonShine\Resources\ShopResource;
use App\MoonShine\Resources\UserResource;
use App\MoonShine\Pages\AnswerReviewPage;
use App\MoonShine\Resources\QuestionResource;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  MoonShine  $core
     * @param  MoonShineConfigurator  $config
     *
     */
    public function boot(CoreContract $core, ConfiguratorContract $config): void
    {
        // $config->authEnable();

        $core
            ->resources([
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                PostResource::class,
                ReviewResource::class,
                ShopResource::class,
                UserResource::class,
                QuestionResource::class,
            ])
            ->pages([
                ...$config->getPages(),
                AnswerReviewPage::class,
            ])
        ;
    }
}
