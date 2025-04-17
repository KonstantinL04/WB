<?php

declare(strict_types=1);

namespace App\MoonShine\Controllers;

use App\Models\Review;
use App\MoonShine\Handlers\ChatGPTReviewHandler;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Http\Controllers\MoonShineController;
use Symfony\Component\HttpFoundation\Response;

final class ReviewController extends MoonShineController
{
    public function __invoke(MoonShineRequest $request): Response
    {
        // Получаем ID отзыва из параметров запроса (например, ?id=123)
        $reviewId = $request->get('id');

        if (!$reviewId) {
            $this->toast('ID отзыва не передан', 'error');
            return back();
        }

        $review = Review::find($reviewId);

        if (!$review) {
            $this->toast('Отзыв не найден', 'error');
            return back();
        }

        // Вызываем обработчик для генерации ответа по конкретному отзыву
        ChatGPTReviewHandler::processReview($review);

        $this->toast('Ответ успешно сгенерирован', 'success');
        return back();
    }
}
