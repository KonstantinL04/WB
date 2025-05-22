<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;

class PublishResponseQuestions extends Handler
{
    /**
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        if (! $this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        /** @var Question $question */
        $question = $this->getResource()->getItem();

        if ($this->isQueue()) {
            MoonShineUI::toast(__('moonshine::ui.resource.queued'));
            return back();
        }

        self::process($question);

        return back();
    }

    public static function process(Question $question): void
    {
        $questionId = $question->question_id;
        $replyText  = $question->response;

        if (empty($questionId) || empty($replyText)) {
            MoonShineUI::toast('Нет ID вопроса или текста ответа', ToastType::ERROR);
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.wildberries.token'),
            'Content-Type'  => 'application/json',
        ])->post('https://feedbacks-api.wildberries.ru/api/v1/questions', [
            'id'        => $questionId,
            'text'      => $replyText,
            'wasViewed' => true,
        ]);

        if ($response->successful()) {
            $question->status = 'Опубликован';
            $question->save();

            MoonShineUI::toast('Ответ на вопрос успешно опубликован', ToastType::ERROR);
        } else {
            Log::error('Ошибка при отправке ответа на вопрос', [
                'question_id' => $questionId,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);
            MoonShineUI::toast('Ошибка при отправке ответа на вопрос', ToastType::ERROR);
        }
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
