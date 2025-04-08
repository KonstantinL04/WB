<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Review;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\MoonShineUI;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;

class GetFeedBacks extends Handler
{
    public function __construct(string $label = 'Импортировать отзывы')
    {
        parent::__construct($label);
    }

    /**
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        if (!$this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        $queryParams = [
            'isAnswered' => false,      // Обрабатываем необработанные отзывы
            'take'       => 10,          // Количество отзывов (максимум 5000)
            'skip'       => 0,          // Количество пропускаемых отзывов
            'order'      => 'dateDesc', // Сортировка: dateAsc или dateDesc
        ];

        $response = Http::withToken(config('services.wildberries.token'))
            ->get('https://feedbacks-api.wildberries.ru/api/v1/feedbacks', $queryParams);

        if (!$response->successful()) {
            MoonShineUI::toast('Ошибка при получении данных', 'error');
            return back();
        }

        $jsonData = $response->json();

        if (!isset($jsonData['data']['feedbacks']) || !is_array($jsonData['data']['feedbacks'])) {
            MoonShineUI::toast('Неверная структура JSON', 'error');
            return back();
        }

        foreach ($jsonData['data']['feedbacks'] as $feedback) {
            // Проверяем наличие и непустоту артикула товара (nmId)
            if (empty($feedback['productDetails']['nmId'])) {
                continue; // Пропускаем отзыв без артикула
            }

            // Ищем или создаём товар по nmId
            $product = Product::firstOrCreate(
                ['nm_id' => $feedback['productDetails']['nmId']],
                [
                    'shop_id'   => 1,
                    'name'      => $feedback['productDetails']['productName'] ?? 'Без названия',
                    'category'  => $feedback['subjectName'] ?? null,
                    'characteristic'=> null,
                    'description' => null,
                ]
            );

            // Если по каким-то причинам товар не создан, пропускаем отзыв
            if (!$product) {
                continue;
            }

            // Импортируем отзыв, используя внешний ключ product_id из таблицы products
            Review::updateOrCreate(
                ['review_id' => $feedback['id']],
                [
                    'product_id' => $product->id, // Здесь будет сохранён автоинкрементный ID из таблицы products
                    'evaluation' => $feedback['productValuation'] ?? null,
                    'name_user'  => $feedback['userName'] ?? null,
                    'photos'     => isset($feedback['photoLinks'])
                        ? json_encode($feedback['photoLinks'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : null,
                    'videos'     => $feedback['video'] ?? null,
                    'sentiment'  => null,
                    'topic_review_id' => null,
                    'pluses'     => $feedback['pros'] ?? null,
                    'cons'       => $feedback['cons'] ?? null,
                    'comment_text'=> $feedback['text'] ?? null,
                    'response'   => null,
                    'status'     => 'новый',
                ]
            );
        }
        return back();
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
