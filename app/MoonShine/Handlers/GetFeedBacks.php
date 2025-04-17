<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Review;
use Carbon\Carbon;
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

        $response = Http::withToken(config('services.wildberries.token'))
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
        $reviewsCount = (int) request()->input('feedback_count', 30);

        $queryParams = [
            'isAnswered' => false,       // Обрабатываем необработанные отзывы
            'take'       => $reviewsCount, // Количество отзывов для получения
            'skip'       => 0,             // Количество пропускаемых отзывов
            'order'      => 'dateDesc',    // Сортировка: dateAsc или dateDesc
        ];

        $response = Http::withToken(config('services.wildberries.token'))
            ->get('https://feedbacks-api.wildberries.ru/api/v1/feedbacks', $queryParams);

        if (!$response->successful()) {
            MoonShineUI::toast('Ошибка при получении данных', ToastType::ERROR);
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
                    'shop_id'       => 1,
                    'name'          => $feedback['productDetails']['productName'] ?? 'Без названия',
                    'category'      => $feedback['subjectName'] ?? null,
                    'characteristic'=> null,
                    'description'   => null,
                ]
            );

            if (!$product) {
                continue;
            }

            // Импортируем отзыв, используя внешний ключ product_id
            Review::updateOrCreate(
                ['review_id' => $feedback['id']],
                [
                    'product_id'     => $product->id,
                    'evaluation'     => $feedback['productValuation'] ?? null,
                    'name_user'      => $feedback['userName'] ?? null,
                    'photos'         => isset($feedback['photoLinks'])
                        ? json_encode($feedback['photoLinks'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : null,
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
        }
        MoonShineUI::toast('Отзывы успешно импортированы', ToastType::SUCCESS);
        return back();
    }
    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
