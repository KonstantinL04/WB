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
    public function __construct(string $label = 'Импортировать вопросы')
    {
        parent::__construct($label);
    }

    /**
     * Получает количество доступных необработанных вопросов "за все время".
     *
     * @return int
     */
    protected function fetchQuestionsCount(): int
    {
        // Устанавливаем период "за все время": с начала эпохи Unix до текущего момента
        $dateFrom = 0;
        $dateTo = Carbon::now()->timestamp;

        $queryParams = [
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'isAnswered' => false, // Отбираем необработанные вопросы
        ];

        $user = auth()->user();

        $activeShop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();
//        $activeShop = auth()->user()->shops()->where('is_active', true)->firstOrFail();
//        $apiKey = Crypt::decryptString($activeShop->api_key);
        $apiKey = $activeShop->api_key;
        $response = Http::withToken($apiKey)
            ->get('https://feedbacks-api.wildberries.ru/api/v1/questions/count', $queryParams);

        if (!$response->successful()) {
            // Если не удалось получить данные, возвращаем 0
            return 0;
        }

        $jsonData = $response->json();
        // Предполагается, что API возвращает значение по ключу "data"
        return isset($jsonData['data']) ? (int)$jsonData['data'] : 0;
    }

    /**
     * Метод для получения доступного количества вопросов.
     *
     * @return int
     */
    public function getAvailableCount(): int
    {
        return $this->fetchQuestionsCount();
    }

    /**
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        // Получаем количество вопросов для импорта из переданных данных, по умолчанию 20
        $questionsCount = (int) request()->input('question_count', 30);

        $queryParams = [
            'isAnswered' => false,      // Импортируем вопросы, на которые ещё не дан ответ
            'take'       => $questionsCount, // Количество вопросов для получения
            'skip'       => 0,           // Количество пропускаемых вопросов
            'order'      => 'dateDesc',  // Сортировка: dateAsc или dateDesc
        ];

        $user = auth()->user();

        $activeShop = $user->active_shop_id
            ? Shop::findOrFail($user->active_shop_id)
            : $user->shops()->where('is_active', true)->firstOrFail();
//        $activeShop = auth()->user()->shops()->where('is_active', true)->firstOrFail();
//        $apiKey = Crypt::decryptString($activeShop->api_key);
        $apiKey = $activeShop->api_key;
        $response = Http::withToken($apiKey)
            ->get('https://feedbacks-api.wildberries.ru/api/v1/questions', $queryParams);

        if (!$response->successful()) {
            MoonShineUI::toast('Ошибка при получении данных', ToastType::ERROR);
            return back();
        }

        $jsonData = $response->json();

        if (!isset($jsonData['data']['questions']) || !is_array($jsonData['data']['questions'])) {
            MoonShineUI::toast('Неверная структура JSON', ToastType::ERROR);
            return back();
        }

        foreach ($jsonData['data']['questions'] as $feedback) {
            // Проверяем наличие артикула товара (nmId) в productDetails
            if (empty($feedback['productDetails']['nmId'])) {
                continue;
            }

            // Проверяем, существует ли вопрос по question_id (из JSON это feedback['id'])
            if (Question::where('question_id', $feedback['id'])->exists()) {
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

            if (! $product) {
                continue;
            }

            // Импортируем вопрос, используя модель Question
            Question::updateOrCreate(
                ['question_id' => $feedback['id']],
                [
                    'product_id'      => $product->id, // Сохраняем автоинкрементный ID товара
                    'name_user'       => $feedback['userName'] ?? null,
                    'question'        => $feedback['text'] ?? null,  // Текст вопроса
                    'sentiment'       => null,
                    'topic_review_id' => null,
                    'response'        => null,
                    'status'          => 'Новый',
                    'created_date'    => isset($feedback['createdDate']) ? Carbon::parse($feedback['createdDate']) : null,
                ]
            );
        }

        MoonShineUI::toast('Вопросы успешно импортированы', ToastType::SUCCESS);
        return back();
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
