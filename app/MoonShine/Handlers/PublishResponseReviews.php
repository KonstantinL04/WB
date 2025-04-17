<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use App\Models\Review;
use Illuminate\Support\Facades\Log;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Exceptions\ActionButtonException;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Laravel\Handlers\Handler;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class PublishResponseReviews extends Handler
{
    /**
     * @throws ActionButtonException
     */
    public function handle(): Response
    {
        if (! $this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        // Получаем модель Review из текущего ресурса
        /** @var Review $review */
        $review = $this->getResource()->getItem();

        if ($this->isQueue()) {
            MoonShineUI::toast(__('moonshine::ui.resource.queued'));
            return back();
        }

        // Вызываем статический метод process
        self::process($review);

        return back();
    }

    /**
     * Выполняет фактическую отправку ответа в Wildberries
     */
    public static function process(Review $review): void
    {
        $reviewId  = $review->review_id;
        $replyText = $review->response;

        if (empty($reviewId) || empty($replyText)) {
            MoonShineUI::toast('Нет ID отзыва или текста ответа', ToastType::ERROR);
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.wildberries.token'),
            'Content-Type'  => 'application/json',
        ])->post('https://feedbacks-api.wildberries.ru/api/v1/feedbacks/answer', [
            'id'   => $reviewId,
            'text' => $replyText,
        ]);

        if ($response->successful()) {
            $review->status = 'Опубликован';
            $review->save();

            MoonShineUI::toast('Ответ опубликован успешно!', ToastType::ERROR);
        } else {
            Log::error('Ошибка при публикации ответа на WB', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            MoonShineUI::toast('Ошибка при отправке ответа', ToastType::ERROR);
        }
    }

    public function getButton(): ActionButtonContract
    {
        return ActionButton::make($this->getLabel(), $this->getUrl());
    }
}
