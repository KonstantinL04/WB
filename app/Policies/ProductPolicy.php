<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\Product;
use App\Models\MoonshineUser;

class ProductPolicy
{
    use HandlesAuthorization;

    public function before(MoonshineUser $user, $ability): ?bool
    {
        if ($user->moonshine_user_role->name === 'Админ') {
            return true;
        }

        return null; // иначе переходим к конкретным методам
    }

    public function viewAny(MoonshineUser $user): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец', 'Сотрудник'], true);
    }

    public function view(MoonshineUser $user, Product $item): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец', 'Сотрудник'], true);
    }

    public function create(MoonshineUser $user): bool
    {
        return false;
    }

    public function update(MoonshineUser $user, Product $item): bool
    {
        return false;
    }

    public function delete(MoonshineUser $user, Product $item): bool
    {
        return false;
    }

    public function restore(MoonshineUser $user, Product $item): bool
    {
        return false;
    }

    public function forceDelete(MoonshineUser $user, Product $item): bool
    {
        return false;
    }

    public function massDelete(MoonshineUser $user): bool
    {
        return false;
    }
}
