<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\Shop;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\MenuItem;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Shop>
 */
class ShopResource extends ModelResource
{
    protected string $model = Shop::class;

    protected bool $withPolicy = true;
    protected string $title = 'Магазины';
    protected bool $columnSelection = true;


    /**
     * @return list<FieldContract>
     */

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        if (auth()->user()->moonshine_user_role->name !== 'Админ') {
            $builder->where('user_id', auth()->id());
        }

        return $builder;
    }
    protected function activeActions(): ListOf
    {
        $actions = parent::activeActions();

        if (! auth()->user()->moonshine_user_role->name === 'Админ') {
            $actions = $actions->except(Action::DELETE);
        }

        return $actions;
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $item->user_id = auth()->id();
        return $item;
    }
    protected function beforeSaving(mixed $item): mixed
    {
        $apiKey = $item->api_key;
        // Логируем переданный API-ключ
        Log::info('Проверка API-ключа', ['api_key' =>$apiKey]);

        // Проверка валидности API-ключа
        $response = Http::withToken($apiKey)
            ->get('https://feedbacks-api.wildberries.ru/api/v1/feedbacks/count', [
                'dateFrom' => 0,
                'dateTo' => now()->timestamp,
                'isAnswered' => false,
            ]);

        if (!$response->successful()) {
            Log::warning('Недействительный API-ключ', [
                'api_key' => $apiKey,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'api_key' => 'Недействительный API-ключ. Пожалуйста, введите корректный ключ.',
            ]);
        }

//        $item->api_key = Crypt::encryptString($apiKey);
        return $item;
    }
    protected function indexButtons(): ListOf
    {
        return parent::indexButtons()
            ->prepend(
                ActionButton::make('Сделать активным')
                    ->method('setActive')
                    ->canSee(fn($item) => ! $item->is_active)
                    ->icon('s.check-circle')
                    ->withConfirm(
                        title: 'Подтверждение',
                        content: 'Сделать этот магазин активным?',
                        button: 'Да'
                    )
            );
    }
    protected function indexFields(): iterable
    {
        return [
            Text::make('Название магазина', 'name')->sortable(),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Название магазина', 'name'),
                Text::make('API-ключ', 'api_key'),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            Text::make('Название магазина', 'name'),
            Text::make('API-ключ', 'api_key'),
        ];
    }

    public function setActive(MoonShineRequest $request): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->getResource()->getItem();

        // Снимаем у всех
        Shop::where('user_id', auth()->id())
            ->update(['is_active' => false]);

        // Устанавливаем у текущего
        $shop->update(['is_active' => true]);

        MoonShineUI::toast('Магазин сделан активным', ToastType::SUCCESS);

        return back();
    }

    /**
     * @param Shop $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
