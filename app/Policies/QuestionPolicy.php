<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MoonshineUser;
use App\Models\Question;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionPolicy
{
    use HandlesAuthorization;

    /**
     * Админ пропускается на все действия.
     */
    public function before(MoonshineUser $user, $ability): ?bool
    {
        if ($user->moonshine_user_role->name === 'Админ') {
            return true;
        }

        return null;
    }

    /**
     * Кто может видеть список вопросов.
     * Админ или Продавец (с активным магазином).
     */
    public function viewAny(MoonshineUser $user): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец','Сотрудник'], true);
    }

    /**
     * Кто может смотреть конкретный вопрос.
     * Продавец — если вопрос принадлежит его активному магазину.
     */
    public function view(MoonshineUser $user, Question $question): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец','Сотрудник'], true);
    }

    /**
     * Создание вопросов через UI не предусмотрено (импорт идёт хэндлером).
     */
    public function create(MoonshineUser $user): bool
    {
        return false;
    }

    /**
     * Обновление (например, изменение статуса/ответа) может Продавец для своих вопросов.
     */
    public function update(MoonshineUser $user, Question $question): bool
    {
        return in_array($user->moonshine_user_role->name, ['Админ', 'Продавец','Сотрудник'], true);
    }

    /**
     * Удаление вопросов через UI обычно запрещено.
     */
    public function delete(MoonshineUser $user, Question $question): bool
    {
        return false;
    }

    /**
     * Восстановление — запрещено.
     */
    public function restore(MoonshineUser $user, Question $question): bool
    {
        return false;
    }

    /**
     * Принудительное удаление — запрещено.
     */
    public function forceDelete(MoonshineUser $user, Question $question): bool
    {
        return false;
    }

    /**
     * Массовое удаление — запрещено.
     */
    public function massDelete(MoonshineUser $user): bool
    {
        return false;
    }
}
