<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\MoonShineUser;

class MoonShineUserPolicy
{
    use HandlesAuthorization;
    public function before(MoonshineUser $user, $ability): ?bool
    {
        if ($user->moonshine_user_role->name === 'Админ') {
            return true;
        }

        return null;
    }

    public function viewAny(MoonshineUser $user): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец'], true);
    }


    public function view(MoonshineUser $user, MoonShineUser $item): bool
    {
        // Продавец может смотреть только пользователей с ролью «Сотрудник»
        if ($user->moonshine_user_role->name === 'Продавец') {
            return $item->moonshine_user_role->name === 'Сотрудник';
        }

        // Для остальных — по before()
        return false;
    }

    public function create(MoonshineUser $user): bool
    {
        return $user->moonshine_user_role->name === 'Продавец';
    }

    public function update(MoonshineUser $user, MoonShineUser $item): bool
    {
        if ($user->moonshine_user_role->name === 'Продавец') {
            return $item->moonshine_user_role->name === 'Сотрудник';
        }

        return false;
    }

    public function delete(MoonshineUser $user, MoonShineUser $item): bool
    {
        return $user->moonshine_user_role->name === 'Продавец';
    }

    public function restore(MoonshineUser $user, MoonShineUser $item): bool
    {
        return false;
    }

    public function forceDelete(MoonshineUser $user, MoonShineUser $item): bool
    {
        return false;
    }

    public function massDelete(MoonshineUser $user): bool
    {
        return false;
    }
}
