<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;

class GetQuestions extends Handler
{
    protected int $batchSize = 30; // размер порции

    public function __construct(string $label = 'Импортировать вопросы')
    {
        parent::__construct($label);
    }

    /** Считаем общее число необработанных вопросов */
    protected function fetchQuestionsCount(): int
    {
        $user = auth()->user();
        $shop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();

        $response = Http::withToken($shop->api_key)
            ->get('https://feedbacks-api.wildberries.ru/api/v1/questions/count', [
                'dateFrom'   => 0,
                'dateTo'     => Carbon::now()->timestamp,
                'isAnswered' => false,
            ]);

        if (! $response->successful()) {
            return 0;
        }

        return (int) ($response->json('data') ?? 0);
    }

    /**
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        $total    = $this->fetchQuestionsCount();
        $imported = 0;
        $skip     = 0;

        if ($total === 0) {
            MoonShineUI::toast('Новых вопросов нет', ToastType::INFO);
            return back();
        }

        $user = auth()->user();
        $shop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();

        while ($skip < $total) {
            $take = min($this->batchSize, $total - $skip);

            $response = Http::withToken($shop->api_key)
                ->get('https://feedbacks-api.wildberries.ru/api/v1/questions', [
                    'isAnswered' => false,
                    'take'       => $take,
                    'skip'       => $skip,
                    'order'      => 'dateDesc',
                ]);

            if (! $response->successful()) {
                Log::error('WB Questions API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                MoonShineUI::toast("Ошибка при импорте вопросов: HTTP {$response->status()}", ToastType::ERROR);
                break;
            }

            $items = $response->json('data.questions', []);
            if (empty($items)) {
                break;
            }

            foreach ($items as $q) {
                if (empty($q['productDetails']['nmId'])) {
                    continue;
                }

                if (Question::where('question_id', $q['id'])->exists()) {
                    continue;
                }

                $product = Product::firstOrCreate(
                    ['nm_id' => $q['productDetails']['nmId']],
                    [
                        'shop_id'  => $shop->id,
                        'name'     => $q['productDetails']['productName'] ?? 'Без названия',
                    ]
                );

                Question::create([
                    'question_id'   => $q['id'],
                    'product_id'    => $product->id,
                    'name_user'     => $q['userName'] ?? null,
                    'question'      => $q['text'] ?? null,
                    'status'        => 'Новый',
                    'created_date'  => isset($q['createdDate'])
                        ? Carbon::parse($q['createdDate'])
                        : null,
                ]);
                $importedNmIds[] = $q['productDetails']['nmId'];

            }

            $imported += count($items);
            $skip     += $take;

        }
        $nmIds = array_values(array_unique($importedNmIds));
        $this->updateProductCards($nmIds);

        MoonShineUI::toast("Импорт завершён: {$imported} из {$total} вопросов", ToastType::SUCCESS);
        return back();
    }
    protected function updateProductCards(array $nmIds): void
    {
        $apiKey = $this->getActiveShopApiKey();

        // Разбиваем на батчи по 100
        foreach (array_chunk($nmIds, 100) as $batch) {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://content-api.wildberries.ru/content/v2/get/cards/list', [
                'settings' => [
                    'filter' => ['withPhoto' => -1],
                    'cursor' => ['limit' => 100],
                ],
                'imtIDs' => [],       // опционально
                'nmIDs'  => $batch,   // важно
            ]);

            if (! $response->successful()) {
                Log::error('Ошибка при получении карточек WB', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                continue;
            }

            $data = $response->json('cards', []);

            foreach ($data as $card) {
                // Ищем локальный товар
                $product = Product::where('nm_id', $card['nmID'])->first();
                if (! $product) {
                    continue;
                }
                // Оригинальные характеристики в JSON
                $rawChars = $card['characteristics'] ?? [];
                $charsJson = json_encode($rawChars, JSON_UNESCAPED_UNICODE);

                // Пытаемся достать Цвет и Страну производства
                $color = null;
                $country = null;
                foreach ($rawChars as $item) {
                    // Название точь‑в‑точь как приходит из API
                    if (isset($item['name'], $item['value'][0])) {
                        switch ($item['name']) {
                            case 'Цвет':
                                $color = $item['value'][0];
                                break 2; // выходим из цикла, если нашли
                        }
                    }
                }
                foreach ($rawChars as $item) {
                    if (isset($item['name'], $item['value'][0])) {
                        if ($item['name'] === 'Страна производства') {
                            $country = $item['value'][0];
                            break;
                        }
                    }
                }

                // Обновляем поля модели
                $product->update([
                    'category' => $card['subjectName'] ?? null,
                    'description'          => $card['description'] ?? null,
                    'characteristics' => $charsJson,
                    'image'                => $card['photos'][0]['big'] ?? null,
                    'color'                => $color,
                    'country_manufacture'  => $country,
                ]);
            }

            // Если в ответе есть cursor и total > limit, надо повторить с новым курсором:
            $cursor = $response->json('cursor');
            if (! empty($cursor['updatedAt']) && $cursor['total'] > count($batch)) {
                // Повторяем до получения всех — см. доку WB о пагинации
            }
        }
    }
    protected function getActiveShopApiKey(): string
    {
        $user = auth()->user();
        $shop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();

        return $shop->api_key;
    }


    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
