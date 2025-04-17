<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Question;
use App\Models\QuestionsTopic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;

class ChatGPTQuestionsHandler extends Handler
{
    public function __construct(string $label = 'Спросить ChatGPT')
    {
        parent::__construct($label);
    }

    /**
     * Обработка для генерации ответа для всех вопросов с нужным статусом.
     *
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        self::process();
        return back();
    }

    /**
     * Массовая обработка вопросов с определенным статусом (например, "Тест")
     */
    public static function process(): void
    {
        // Получаем все доступные тематики для вопросов из базы
        $availableTopics = QuestionsTopic::pluck('name_topic', 'id')->toArray();
        $topicsString = implode(', ', $availableTopics);

        // Выбираем вопросы для анализа (например, те, у которых статус "Тест")
        $questions = Question::where('status', 'Новый')->get();

        foreach ($questions as $questionItem) {
            self::processQuestion($questionItem, $availableTopics, $topicsString);
        }
    }

    /**
     * Обработка конкретного вопроса для формирования ответа.
     *
     * @param Question $questionItem
     * @param array|null $availableTopics Массив тем, если уже получен
     * @param string|null $topicsString Строка со списком тем
     */
    public static function processQuestion(Question $questionItem, ?array $availableTopics = null, ?string $topicsString = null): void
    {
        // Если список тем не передан, получаем его
        if (is_null($availableTopics)) {
            $availableTopics = QuestionsTopic::pluck('name_topic', 'id')->toArray();
        }
        if (is_null($topicsString)) {
            $topicsString = implode(', ', $availableTopics);
        }

        $productName = $questionItem->product ? $questionItem->product->name : 'Не указано';
        $questionText = "Наименование товара: {$productName}\n" .
            "Вопрос: " . ($questionItem->question ?: "нет");
        $clientName = $questionItem->name_user ?: 'Уважаемый клиент';
        $greeting = "Здравствуйте, {$clientName}!";
        $prompt = <<<PROMPT
Доступные тематики: {$topicsString}.

Представь, что ты лучший специалист по работе с вопросами на маркетплейсах.
Проанализируй следующий вопрос, учитывая наименование товара и имя клиента.
Если имя указано, обязательно используй его в приветствии (например, "{$greeting}").
Выбери одну тематику, которая наилучшим образом характеризует вопрос, и сформируй персонализированный, человечный ответ на вопрос.

Также оцени типичность вопроса:
- Если можно дать уверенный и понятный ответ без дополнительной информации, пометь как "Типовой".
- Если требуется вмешательство сотрудника для уточнения информации, пометь как "Нетиповой".

Вопрос:
{$questionText}

Верни ответ в формате JSON с ключами:
 - topic: выбранная тематика (строка)
 - reply: сформированный ответ
 - sentiment: "Типовой" или "Нетиповой"
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.proxyapi.api_key'),
            'Content-Type'  => 'application/json',
        ])->post(
            config('services.proxyapi.base_url') . '/chat/completions',
            [
                'model'    => config('services.proxyapi.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты эксперт по анализу вопросов.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 300,
            ]
        );

        if (!$response->successful()) {
            Log::error('ChatGPT API Error for Question', [
                'question_id' => $questionItem->id,
                'status'      => $response->status(),
                'response'    => $response->body(),
            ]);
            return;
        }

        Log::info('ChatGPT API Response for Question', [
            'question_id' => $questionItem->id,
            'response'    => $response->json(),
        ]);

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? null;

        if ($content) {
            // Удаляем markdown-обрамление (например, ```json ... ```)
            $cleanContent = trim(preg_replace('/^```(json)?\s*|```$/i', '', $content));
            $analysis = json_decode($cleanContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($analysis)) {
                $selectedTopic = $analysis['topic'] ?? null;
                // Ищем ID тематики по имени из базы (QuestionsTopic)
                $topicId = array_search($selectedTopic, $availableTopics);
                if ($topicId !== false) {
                    $questionItem->topic_review_id = $topicId;
                }
                $questionItem->response = $analysis['reply'] ?? null;
                $questionItem->sentiment = $analysis['sentiment'] ?? null;
                $questionItem->status = 'Сформирован';
                MoonShineUI::toast('Ответы успешно импортированы', ToastType::SUCCESS);
                $questionItem->save();
            } else {
                Log::error('JSON decoding error in Question processing', [
                    'question_id'  => $questionItem->id,
                    'clean_content'=> $cleanContent,
                    'json_error'   => json_last_error_msg(),
                ]);
            }
        } else {
            Log::warning('Empty content received from ChatGPT for Question', [
                'question_id' => $questionItem->id,
            ]);
        }
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
