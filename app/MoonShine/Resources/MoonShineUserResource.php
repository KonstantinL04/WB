<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\MoonshineUser;
use App\Models\MoonshineUserRole;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Text;

#[Icon('users')]
#[Group('moonshine::ui.resource.system', 'users', translatable: true)]
#[Order(1)]
/**
 * @extends ModelResource<MoonshineUser>
 */
class MoonShineUserResource extends ModelResource
{
    protected string $model = MoonshineUser::class;

    protected string $column = 'name';

    protected array $with = ['moonshine_user_role'];

    protected bool $simplePaginate = true;
    protected bool $withPolicy = true;

    protected bool $columnSelection = true;

    public function getTitle(): string
    {
        return __('moonshine::ui.resource.admins_title');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW);
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $seller = auth()->user();

        // Находим активный магазин продавца
        $activeShopId = $seller
            ->shops()
            ->where('is_active', true)
            ->value('id');

        Log::info('Создаём сотрудника для продавца', [
            'seller_id'      => $seller->id,
            'active_shop_id' => $activeShopId,
        ]);

        // Привязываем сотрудника к продавцу и к тому же магазину
        $item->seller_id      = $seller->id;
        $item->active_shop_id = $activeShopId;

        return $item;
    }
    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $role = auth()->user()->moonshine_user_role->name;

        // Админ видит всех
        if ($role === 'Админ') {
            return $builder;
        }

        // Продавец видит только сотрудников, привязанных к нему
        if ($role === 'Продавец') {
            return $builder
                ->where('seller_id', auth()->id())
                ->whereHas('moonshine_user_role', fn($q) => $q->where('name', 'Сотрудник'));
        }

        return $builder;
    }
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(
                __('moonshine::ui.resource.role'),
                'moonshine_user_role',
                formatted: static fn (MoonshineUserRole $model) => $model->name,
                resource: MoonShineUserRoleResource::class,
            )->badge(Color::PURPLE),

            Text::make(__('moonshine::ui.resource.name'), 'name'),

            Image::make(__('moonshine::ui.resource.avatar'), 'avatar')->modifyRawValue(fn (
                ?string $raw
            ): string => $raw ?? ''),

            Date::make(__('moonshine::ui.resource.created_at'), 'created_at')
                ->format("d.m.Y")
                ->sortable(),

            Email::make(__('moonshine::ui.resource.email'), 'email')
                ->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return $this->indexFields();
    }

    protected function formFields(): iterable
    {
        // Определяем доступные роли в зависимости от роли залогиненного
        $currentRole = auth()->user()->moonshine_user_role->name;

        // Если Админ, то все роли
        if ($currentRole === 'Админ') {
            $rolesQuery = fn(Builder $q) => $q->select(['id', 'name']);
        }
        // Если Продавец, то только роль "Сотрудник"
        elseif ($currentRole === 'Продавец') {
            $rolesQuery = fn(Builder $q) => $q
                ->select(['id', 'name'])
                ->where('name', 'Сотрудник');
        }
        // Иначе (Сотрудник) — вообще никогда не должен сюда попасть, но на всякий случай — пусто
        else {
            $rolesQuery = fn(Builder $q) => $q->select(['id', 'name'])->whereRaw('0 = 1');
        }

        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('Основное'), [
                        ID::make()->sortable(),

                        BelongsTo::make(
                            __('Роль'),
                            'moonshine_user_role',
                            // как отображается
                            formatted: static fn (MoonshineUserRole $r) => $r->name,
                            resource: MoonShineUserRoleResource::class,
                        )
                            ->valuesQuery($rolesQuery)  // вот здесь фильтрация
                            ->required(),

                        Flex::make([
                            Text::make(__('Имя'), 'name')->required(),
                            Email::make(__('E-mail'), 'email')->required(),
                        ]),

                        Image::make(__('Аватар'), 'avatar')
                            ->disk(moonshineConfig()->getDisk())
                            ->dir('moonshine_users'),

                        Date::make(__('Дата создания'), 'created_at')
                            ->format('d.m.Y')
                            ->default(now()->toDateTimeString()),
                    ])->icon('user-circle'),

                    Tab::make(__('Пароль'), [
                        Collapse::make(__('Сменить пароль'), [
                            Password::make(__('Пароль'), 'password')->eye(),
                            PasswordRepeat::make(__('Повтор пароля'), 'password_repeat')->eye(),
                        ])->icon('lock-closed'),
                    ])->icon('lock-closed'),
                ]),
            ]),
        ];
    }


    /**
     * @return array{name: array|string, moonshine_user_role_id: array|string, email: array|string, password: array|string}
     */
    protected function rules($item): array
    {
        return [
            'name' => 'required',
            'moonshine_user_role_id' => 'required',
            'email' => [
                'sometimes',
                'bail',
                'required',
                'email',
                Rule::unique('moonshine_users')->ignoreModel($item),
            ],
            'password' => $item->exists
                ? 'sometimes|nullable|min:6|required_with:password_repeat|same:password_repeat'
                : 'required|min:6|required_with:password_repeat|same:password_repeat',
        ];
    }

    protected function search(): array
    {
        return [
            'id',
            'name',
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                __('moonshine::ui.resource.role'),
                'moonshine_user_role',
                formatted: static fn (MoonshineUserRole $model) => $model->name,
                resource: MoonShineUserRoleResource::class,
            )->valuesQuery(static fn (Builder $q) => $q->select(['id', 'name'])),

            Email::make('E-mail', 'email'),
        ];
    }
}
