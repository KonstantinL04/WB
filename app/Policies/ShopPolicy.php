<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MoonshineUser;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\Shop;


class ShopPolicy
{
    use HandlesAuthorization;

    /**
     * Перед всеми проверками: если это админ — разрешаем всё.
     */
    public function before(MoonshineUser $user, $ability): ?bool
    {
        if ($user->moonshine_user_role->name === 'Админ') {
            return true;
        }

        return null; // иначе переходим к конкретным методам
    }

    /**
     * Кто может видеть список магазинов.
     * Менеджер и выше — да, обычный пользователь — только свои (view handles это).
     */
    public function viewAny(MoonshineUser $user): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец'], true);
    }

    /**
     * Кто может смотреть детали конкретного магазина.
     * Разрешаем, если это менеджер/пользователь и магазин ему принадлежит.
     */
    public function view(MoonshineUser $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }

    /**
     * Создание магазинов: только менеджер и админ.
     */
    public function create(MoonshineUser $user): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец'], true);
    }

    /**
     * Обновление: менеджер может свой, админ — любой (через before).
     */
    public function update(MoonshineUser $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }

    /**
     * Удаление: только админ (other roles — нет).
     */
    public function delete(MoonshineUser $user, Shop $shop): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец'], true);
    }

    /**
     * Восстановление (если soft deletes) — аналогично удалению.
     */
    public function restore(MoonshineUser $user, Shop $shop): bool
    {
        return false;
    }

    /**
     * Принудительное удаление — только админ.
     */
    public function forceDelete(MoonshineUser $user, Shop $shop): bool
    {
        return false;
    }

    /**
     * Массовое удаление — только админ.
     */
    public function massDelete(MoonshineUser $user): bool
    {
        return false;
    }
}
