<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MoonshineUser;
use App\Models\Review;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;

    /**
     * Пропускаем все проверки для Админа
     */
    public function before(MoonshineUser $user, $ability): ?bool
    {
        if ($user->moonshine_user_role->name === 'Админ') {
            return true;
        }

        return null;
    }

    /**
     * Кто может видеть список отзывов.
     * Админ — всегда, Продавец — если есть активный магазин.
     */
    public function viewAny(MoonshineUser $user): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец','Сотрудник'], true);
    }

    /**
     * Кто может смотреть конкретный отзыв.
     * Продавец — если отзыв принадлежит его активному магазину.
     */
    public function view(MoonshineUser $user, Review $review): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец','Сотрудник'], true);
    }

    /**
     * Создание отзывов через UI не предусмотрено (импорт через Handler).
     * Можно вернуть false или разрешить в зависимости от роли.
     */
    public function create(MoonshineUser $user): bool
    {
        return false;
    }

    /**
     * Обновлять отзыв (например, менять статус/ответ) может Продавец для своих отзывов.
     */
    public function update(MoonshineUser $user, Review $review): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец','Сотрудник'], true);
    }

    /**
     * Удаление отзывов через UI обычно запрещено.
     */
    public function delete(MoonshineUser $user, Review $review): bool
    {
        return false;
    }

    // Остальные методы можно либо запретить, либо настроить аналогично update/delete:

    public function restore(MoonshineUser $user, Review $review): bool
    {
        return false;
    }

    public function forceDelete(MoonshineUser $user, Review $review): bool
    {
        return false;
    }

    public function massDelete(MoonshineUser $user): bool
    {
        return false;
    }
}
