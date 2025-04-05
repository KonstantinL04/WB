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

        $response = Http::withToken('eyJhbGciOiJFUzI1NiIsImtpZCI6IjIwMjUwMjE3djEiLCJ0eXAiOiJKV1QifQ.eyJlbnQiOjEsImV4cCI6MTc1ODYxMjQ5MywiaWQiOiIwMTk1YzlhMC03ZDI2LTcyZWYtOWQ1OS1lYWRiNDRiOWE4YWUiLCJpaWQiOjczMDY0NTM3LCJvaWQiOjM5NDk3NjgsInMiOjEzMCwic2lkIjoiNGRmMThhYTktZWJjNC00Y2U2LTg4MWItZTFlNDg0MzM0ZjVlIiwidCI6ZmFsc2UsInVpZCI6NzMwNjQ1Mzd9.XBa8WN6oEGi_dJEMOb57aHAt1MDYnXGJhSwnbxLi93-DGlP9DiYe1zCmp7ImxTahsc6PQYE9FmXyg9lCPlvWPg')
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
                MoonShineUI::toast("Отзыв {$feedback['id']} не содержит артикула товара", 'warning');
                continue; // Пропускаем отзыв без артикула
            }

            // Ищем или создаём товар по nmId
            $product = Product::firstOrCreate(
                ['nm_id' => $feedback['productDetails']['nmId']],
                [
                    // Здесь необходимо установить shop_id согласно логике вашего приложения.
                    'shop_id'   => 1,
                    'name'      => $feedback['productDetails']['productName'] ?? 'Без названия',
                    // Используем subjectName для категории или можно задать иное значение
                    'category'  => $feedback['productDetails']['subjectName'] ?? null,
                    'description' => null,
                ]
            );

            // Если по каким-то причинам товар не создан, пропускаем отзыв
            if (!$product) {
                MoonShineUI::toast("Не удалось создать товар для отзыва {$feedback['id']}", 'error');
                continue;
            }

            // Импортируем отзыв, используя внешний ключ product_id из таблицы products
            Review::updateOrCreate(
                ['review_id' => $feedback['id']],
                [
                    'product_id' => $product->id, // Здесь будет сохранён автоинкрементный ID из таблицы products
                    'evaluation' => $feedback['productValuation'] ?? null,
                    'name_user'  => $feedback['userName'] ?? null,
                    'text'       => $feedback['text'] ?? null,
                    'photos'     => isset($feedback['photoLinks'])
                        ? json_encode($feedback['photoLinks'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : null,
                    'videos'     => $feedback['video'] ?? null,
                    'sentiment'  => null, // Поле не заполняем
                    'topic_review_id' => null,
                    'status'     => 'новый',
                    'pluses'     => $feedback['pros'] ?? null,
                    'cons'       => $feedback['cons'] ?? null,
                ]
            );
        }


        // Преобразуем полученные данные в отформатированный JSON с сохранением кириллицы
        $content = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'feedbacks.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
