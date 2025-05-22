<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\Modal;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\MoonShineUI;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\Number;
use Symfony\Component\HttpFoundation\Response;

class GetFeedBacks extends Handler
{
    protected int $batchSize = 30;
    public function __construct(string $label = 'Импортировать отзывы')
    {
        parent::__construct($label);
    }

    /**
     * Получает количество доступных необработанных отзывов "за все время".
     *
     * @return int
     */
    protected function fetchFeedbackCount(): int
    {
        // Устанавливаем период "за все время": с начала эпохи Unix до текущего момента
        $dateFrom = 0;
        $dateTo = Carbon::now()->timestamp;

        $queryParams = [
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'isAnswered' => false, // Отбираем необработанные отзывы
        ];

        $user = auth()->user();

        $activeShop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();
        $apiKey = $activeShop->api_key;
        $response = Http::withToken($apiKey)
            ->get('https://feedbacks-api.wildberries.ru/api/v1/feedbacks/count', $queryParams);

        if (!$response->successful()) {
            // Если не удалось получить данные, возвращаем 0
            return 0;
        }

        $jsonData = $response->json();
        // Предполагается, что API возвращает значение по ключу "data"
        return isset($jsonData['data']) ? (int)$jsonData['data'] : 0;
    }

    /**
     * Метод для получения доступного количества отзывов.
     *
     * @return int
     */
    public function getAvailableCount(): int
    {
        return $this->fetchFeedbackCount();
    }

    /**
     * @throws \MoonShine\UI\Exceptions\ActionButtonException
     */
    public function handle(): Response
    {
        // Получаем количество отзывов для импорта из переданных данных, по умолчанию 20
        $reviewsCount = (int) request()->input(
            'feedback_count',
            $this->getAvailableCount()
        );

        $queryParams = [
            'isAnswered' => false,       // Обрабатываем необработанные отзывы
            'take'       => $reviewsCount, // Количество отзывов для получения
            'skip'       => 0,             // Количество пропускаемых отзывов
            'order'      => 'dateDesc',    // Сортировка: dateAsc или dateDesc
        ];

        $user = auth()->user();

        $activeShop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();
        $apiKey = $activeShop->api_key;
        $response = Http::withToken($apiKey)
            ->get('https://feedbacks-api.wildberries.ru/api/v1/feedbacks', $queryParams);

        if (!$response->successful()) {
            // Логируем подробности ответа
            \Illuminate\Support\Facades\Log::error('WB Feedbacks API error', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            // Показываем юзеру и в UI информацию о коде ошибки
            MoonShineUI::toast(
                "Ошибка при получении данных: HTTP {$response->status()}",
                ToastType::ERROR
            );

            return back();
        }


        $jsonData = $response->json();

        if (!isset($jsonData['data']['feedbacks']) || !is_array($jsonData['data']['feedbacks'])) {
            MoonShineUI::toast('Неверная структура JSON', ToastType::ERROR);
            return back();
        }

        foreach ($jsonData['data']['feedbacks'] as $feedback) {
            // Если отсутствует артикул товара (nmId), пропускаем отзыв
            if (empty($feedback['productDetails']['nmId'])) {
                continue;
            }

            // Проверяем, существует ли отзыв по review_id (из JSON это feedback['id'])
            if (Review::where('review_id', $feedback['id'])->exists()) {
                continue;
            }

            // Ищем или создаём товар по nmId
            $product = Product::firstOrCreate(
                ['nm_id' => $feedback['productDetails']['nmId']],
                [
                    'shop_id'       => $activeShop->id,
                    'name'          => $feedback['productDetails']['productName'] ?? 'Без названия',
                    'category'      => $feedback['subjectName'] ?? null,
                    'characteristic'=> null,
                    'description'   => null,
                ]
            );

            if (!$product) {
                continue;
            }

            if (! empty($feedback['photoLinks']) && is_array($feedback['photoLinks'])) {
                // Берём только fullSize
                $fullSizeUrls = array_map(
                    fn(array $link): string => $link['fullSize'] ?? '',
                    $feedback['photoLinks']
                );

                // Удаляем пустые строки (если какие‑то записаны без fullSize)
                $fullSizeUrls = array_filter($fullSizeUrls);

                // Кодируем в JSON для хранения
                $photosJson = json_encode(
                    array_values($fullSizeUrls),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } else {
                $photosJson = null;
            }

            // Импортируем отзыв, используя внешний ключ product_id
            Review::updateOrCreate(
                ['review_id' => $feedback['id']],
                [
                    'product_id'     => $product->id,
                    'evaluation'     => $feedback['productValuation'] ?? null,
                    'name_user'      => $feedback['userName'] ?? null,
                    'photos'       => $photosJson,
                    'videos'         => $feedback['video'] ?? null,
                    'sentiment'      => null,
                    'topic_review_id'=> null,
                    'pluses'         => $feedback['pros'] ?? null,
                    'cons'           => $feedback['cons'] ?? null,
                    'comment_text'   => $feedback['text'] ?? null,
                    'response'       => null,
                    'status'         => 'Новый',
                    'created_date'   => isset($feedback['createdDate']) ? Carbon::parse($feedback['createdDate']) : null,
                ]
            );
            $importedNmIds[] = $feedback['productDetails']['nmId'];

        }
        $nmIds = array_values(array_unique($importedNmIds));
        $this->updateProductCards($nmIds);
        MoonShineUI::toast('Отзывы успешно импортированы', ToastType::SUCCESS);
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
