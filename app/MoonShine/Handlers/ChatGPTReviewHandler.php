<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Review;
use App\Models\ReviewTopic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;

class ChatGPTReviewHandler extends Handler
{
    public function __construct(string $label = 'Начать формирование')
    {
        parent::__construct($label);
    }

    /**
     * Обработка для формирования ответов для всех отзывов с определённым статусом.
     *
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        self::process();
        return back();
    }

    /**
     * Массовая обработка отзывов с нужным статусом (например, "новый4").
     */
    public static function process(): void
    {
        $availableTopics = ReviewTopic::pluck('name_topic', 'id')->toArray();
        $reviews = Review::where('status', 'Новый')->get();

        foreach ($reviews as $review) {
            self::processReview($review, $availableTopics);
        }
    }

    /**
     * Обработка конкретного отзыва для формирования ответа.
     *
     * @param Review $review
     * @param array|null $availableTopics (опционально, если список уже получен)
     */
    public static function processReview(Review $review, ?array $availableTopics = null): void
    {
        // Если список тем не передан, получаем его из базы
        $availableTopics = $availableTopics ?? ReviewTopic::pluck('name_topic', 'id')->toArray();
        $topicsString = implode(', ', $availableTopics);

        $productName = $review->product ? $review->product->name : 'Не указано';
        $reviewText = "Наименование товара: {$productName}\n" .
            "Достоинства: " . ($review->pluses ?: "нет") . "\n" .
            "Недостатки: " . ($review->cons ?: "нет") . "\n" .
            "Комментарий: " . ($review->comment_text ?: "нет");
        $clientName = $review->name_user ?: 'Уважаемый клиент';
        $evaluation = $review->evaluation ?? 'Оценка не указана';
        $prompt = <<<PROMPT
Доступные тематики: {$topicsString}.

Представь, что ты лучший специалист по работе с отзывами на маркетплейсах. Проанализируй следующий отзыв, который содержит информацию о товаре, достоинствах, недостатках, комментариях и оценке. Имя клиента: "{$clientName}". Если имя указано, обязательно используй его в приветствии.

Оценка отзыва: {$evaluation}.

Определи:
- одну тематику, которая наилучшим образом характеризует отзыв;
- тональность отзыва (положительная, отрицательная, нейтральная);
- и составь персонализированный, человечный ответ, обязательно начиная с "Здравствуйте, {$clientName}!" (или соответствующего приветствия).

🔽 Что должен включать ответ:
Учти, что:
- Если оценка 1 или 2, но текст отзыва положительный , добавь в ответ замечание о том, что клиент, возможно, ошибся при выставлении оценки и стоит пересмотреть ее на более высокую.
- Если числовая оценка противоречит эмоциональной окраске текста, система должна в первую очередь опираться на содержание текста.

— Если отзыв положительный:
    * Поблагодари клиента;
    * Подчеркни, что вам приятно;
    * Пригласи снова или пожелай хорошего дня.
    * Если {$evaluation} отзыва 1 или 2, но тональность положительная, добавь в ответ замечание о том, что клиент, возможно, ошибся при выставлении оценки и стоит пересмотреть ее на более высокую.
— Если отзыв нейтральный:
    * Поблагодари;
    * Покажи, что ценишь мнение;
    * Сообщи, что обязательно учтёшь всё сказанное.
— Если отзыв отрицательный:
    * Извинись;
    * Вырази сочувствие;
    * Пообещай разобраться;
    * Предложи обратиться в поддержку.

Вот отзыв:
{$reviewText}

Верни JSON:
{
  "topic": "выбранная тематика",
  "tone": "тональность",
  "reply": "ответ на отзыв"
}
PROMPT;


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.proxyapi.api_key'),
            'Content-Type'  => 'application/json',
        ])->post(
            config('services.proxyapi.base_url') . '/chat/completions', [
                'model'    => config('services.proxyapi.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты эксперт по анализу отзывов.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 300,
            ]
        );

        if (!$response->successful()) {
            Log::error('ChatGPT API Error', [
                'review_id' => $review->id,
                'status'    => $response->status(),
                'response'  => $response->body(),
            ]);
            return;
        }

        Log::info('ChatGPT API Response for review', [
            'review_id' => $review->id,
            'response'  => $response->json(),
        ]);

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? null;

        if ($content) {
            // Удаляем обрамление в виде ```json и ``` (если оно есть)
            $cleanContent = trim(preg_replace('/^```(json)?\s*|```$/i', '', $content));
            $analysis = json_decode($cleanContent, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($analysis)) {
                $selectedTopic = $analysis['topic'] ?? null;
                $topicId = array_search($selectedTopic, $availableTopics);
                if ($topicId !== false) {
                    $review->topic_review_id = $topicId;
                }
                $review->sentiment = $analysis['tone'] ?? null;
                $review->response = $analysis['reply'] ?? null;
                $review->status = 'Сформирован';
                MoonShineUI::toast('Ответы успешно импортированы', ToastType::SUCCESS);
                $review->save();
            } else {
                Log::error('JSON decoding error in processReview', [
                    'review_id'    => $review->id,
                    'clean_content'=> $cleanContent,
                    'json_error'   => json_last_error_msg(),
                ]);
            }
        } else {
            Log::warning('Empty content received from ChatGPT in processReview', [
                'review_id' => $review->id,
            ]);
        }
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
