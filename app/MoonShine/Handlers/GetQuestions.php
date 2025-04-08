<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        if (! $this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        $queryParams = [
            'isAnswered' => false,      // Импортируем вопросы, на которые ещё не дан ответ
            'take'       => 10,          // Количество вопросов (максимум 5000)
            'skip'       => 0,           // Количество пропускаемых вопросов
            'order'      => 'dateDesc',  // Сортировка: dateAsc или dateDesc
        ];

        // Получаем вопросы из API Wildberries, используя API-ключ из .env (через config)
        $response = Http::withToken(config('services.wildberries.token'))
            ->whereNull('sentiment')
            ->whereNull('topic_review_id')
            ->get('https://feedbacks-api.wildberries.ru/api/v1/questions', $queryParams);

        if (! $response->successful()) {
//            MoonShineUI::toast('Ошибка при получении данных', 'error');
            return back();
        }

        $jsonData = $response->json();

        if (!isset($jsonData['data']['questions']) || !is_array($jsonData['data']['questions'])) {
//            MoonShineUI::toast('Неверная структура JSON', 'error');
            return back();
        }

        foreach ($jsonData['data']['questions'] as $feedback) {
            // Проверяем наличие артикула товара (nmId) в productDetails
            if (empty($feedback['productDetails']['nmId'])) {
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

            if (! $product) {
                continue;
            }

            // Импортируем вопрос с использованием модели Question
            Question::updateOrCreate(
                ['question_id' => $feedback['id']],
                [
                    'product_id' => $product->id, // Сохраняем автоинкрементный ID товара
                    'name_user'  => $feedback['userName'] ?? null,
                    'question'   => $feedback['text'] ?? null,  // Текст вопроса
                    'sentiment'  => null,
                    'topic_review_id' => null,
                    'response'   => null,
                    'status'     => 'новый',
                ]
            );
        }

//        MoonShineUI::toast('Вопросы успешно импортированы', 'success');
        return back();
        // Преобразуем полученные данные в отформатированный JSON с сохранением кириллицы
//        $content = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//
//        return response()->streamDownload(function () use ($content) {
//            echo $content;
//        }, 'questions.json', [
//            'Content-Type' => 'application/json',
//        ]);
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
