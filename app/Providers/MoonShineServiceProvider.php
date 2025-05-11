<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\MoonshineUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\ResourceContract;
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
use MoonShine\Laravel\Enums\Ability;
use App\MoonShine\Resources\ProductResource;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  MoonShine  $core
     * @param  MoonShineConfigurator  $config
     *
     */
    public function boot(CoreContract $core, ConfiguratorContract $config): void
    {
//        $config->authorizationRules(
//            function ($resource, MoonshineUser $user, Ability $ability, $item): bool {
//                // Админ (роль = 'admin') — полный доступ
//                if ($user->moonshine_user_role->name === 'Админ') {
//                    return true;
//                }
//                // Менеджер может CREATE/VIEW/UPDATE своих, обычный — только VIEW_ANY и VIEW
//                if ($user->moonshine_user_role->name === 'Продавец') {
//                    return true;
//                }
//                // Обычный пользователь — только просмотр
//                return in_array($ability, [Ability::VIEW_ANY, Ability::VIEW], true);
//            }
//        );

        $core
            ->resources([
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                ReviewResource::class,
                ShopResource::class,
                UserResource::class,
                QuestionResource::class,
                ProductResource::class,
            ])
            ->pages([
                ...$config->getPages(),
                AnswerReviewPage::class,
            ])
        ;
    }
}
