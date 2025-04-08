<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Product;
use App\Models\Question;
use App\Models\QuestionsTopic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        self::process();
        return back();
    }

    /**
     * Массовая обработка вопросов с определенным статусом (например, "новый")
     */
    public static function process(): void
    {
        // Получаем все доступные тематики для вопросов из базы (предполагается, что они хранятся в таблице questions_topics)
        $availableTopics = QuestionsTopic::pluck('name_topic', 'id')->toArray();
        $topicsString = implode(', ', $availableTopics);

        // Выбираем вопросы для анализа (например, те, у которых статус "новый")
        $questions = Question::where('status', 'новый')->get();

        foreach ($questions as $questionItem) {
            $productName = $questionItem->product ? $questionItem->product->name : 'Не указано';
            $questionText = "Наименование товара: {$productName}\n" .
                "Вопрос: " . ($questionItem->question ?: "нет");

            $clientName = $questionItem->name_user ?: 'Уважаемый клиент';
            $greeting = "Здравствуйте, {$clientName}!";

            // Формируем prompt для ChatGPT.
            // Здесь не требуется определять тональность, поэтому JSON-ответ будет содержать только ключи "topic" и "reply".
            $prompt = <<<PROMPT
Доступные тематики: {$topicsString}.

Представь, что ты лучший специалист по работе с вопросами на маркетплейсах.
Проанализируй следующий вопрос, учитывая наименование товара и имя клиента.
Если имя указано, обязательно используй его в приветствии (например, "{$greeting}").
Выбери одну тематику, которая наилучшим образом характеризует вопрос, и сформируй персонализированный, человечный ответ на вопрос.

Вопрос:
{$questionText}

Верни ответ в формате JSON с ключами:
 - topic: выбранная тематика (строка)
 - reply: сформированный ответ
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
                continue;
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
                    // Ищем ID тематики по имени из базы вопросов (QuestionsTopic)
                    $topicId = array_search($selectedTopic, $availableTopics);
                    if ($topicId !== false) {
                        $questionItem->topic_review_id = $topicId;
                    }
                    $questionItem->response = $analysis['reply'] ?? null;
                    // В данном случае тональность не требуется, поэтому поле sentiment оставляем как null
                    $questionItem->status = 'Сформирован';
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
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
