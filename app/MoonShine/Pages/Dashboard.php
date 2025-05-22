<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MoonShine\Apexcharts\Components\DonutChartMetric;
use MoonShine\Apexcharts\Components\LineChartMetric;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Modal;
use MoonShine\UI\Fields\DateRange;


#[\MoonShine\MenuManager\Attributes\SkipMenu]
class Dashboard extends Page
{
    protected ?string $alias = 'dashboard';

    public function getTitle(): string
    {
        return $this->title ?: 'Статистика';
    }

    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }



    /**
     * Собираем компоненты дашборда с учётом фильтров
     *
     * @return array<int, ComponentContract>
     * @throws \Exception
     */
    public function components(): array
    {
        // 1) Форма фильтров
        $filterForm = FormBuilder::make()
            ->method(FormMethod::GET) // <-- обязательно
            ->action(url()->current()) // или route/to_page
            ->fields([
                DateRange::make('Период', 'period')
                    ->fromTo('date_from','date_to')
            ])
            ->submit('Применить');

        // 1) Получаем выбранный диапазон из фильтров
        $from = request('period.date_from');
        $to   = request('period.date_to');

        // 2) Базовый запрос по месяцам и статусам
        $baseQuery = DB::table('reviews')
            ->selectRaw("to_char(created_date, 'YYYY-MM') as month, status, COUNT(*) as cnt")
            ->groupBy('month', 'status')
            ->orderBy('month');

        if ($from) {
            // приводим к началу дня
            $baseQuery->where('created_date', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            // приводим к концу дня
            $baseQuery->where('created_date', '<=', Carbon::parse($to)->endOfDay());
        }

        $raw = $baseQuery->get();

        // 3) Собираем уникальные месяцы и статусы
        $months   = $raw->pluck('month')->unique()->values()->toArray();
        $statuses = $raw->pluck('status')->unique()->values()->toArray();

        $translatedMonths = [];
        foreach ($months as $month) {
            $translatedMonths[$month] = Carbon::parse($month . '-01')->translatedFormat('F');
        }

        // 4) Инициализируем шаблон series: для каждого статуса массив нулей
        $series = [];
        foreach ($statuses as $status) {
            foreach ($months as $month) {
                $translated = $translatedMonths[$month];
                $series[$status][$translated] = 0;
            }
        }
        foreach ($raw as $row) {
            $translated = $translatedMonths[$row->month];
            $series[$row->status][$translated] = (int)$row->cnt;
        }

        // 6) Собираем цвета для статусов (можете поменять под свою палитру)
        $palette = [
            'Новый'       => '#a855f7', // Пурпурный
            'Сформирован' => '#facc15', // Жёлтый
            'Опубликован' => '#22c55e', // Зелёный
        ];
        $colors = array_map(fn($s) => $palette[$s] ?? '#a3a3a3', $statuses);

        // 7) Пончиковая диаграмма для оценок, тоже с учётом фильтра
        $ratingQuery = DB::table('reviews')
            ->when($from, fn($q)=>$q->where('created_date','>=',$from))
            ->when($to,   fn($q)=>$q->where('created_date','<=',$to))
            ->select('evaluation',DB::raw('COUNT(*) as cnt'))
            ->groupBy('evaluation')
            ->orderBy('evaluation');

        if ($from) {
            $ratingQuery->where('created_date', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to) {
            $ratingQuery->where('created_date', '<=', Carbon::parse($to)->endOfDay());
        }

        $ratings = $ratingQuery->pluck('cnt', 'evaluation')->toArray();
        ksort($ratings);

        $formattedRatings = [];
        foreach ($ratings as $star => $cnt) {
            $label = match ((int)$star) {
                1        => '1 звезда',
                2,3,4    => "{$star} звезды",
                5        => '5 звёзд',
                default  => "{$star} звёзд",
            };
            $formattedRatings[$label] = $cnt;
        }

        $ratingColors = [];
        foreach ($formattedRatings as $label => $count) {
            $color = match (true) {
                str_starts_with($label, '1') => '#dc2626', // Красный
                str_starts_with($label, '2') => '#f97316', // Оранжевый
                str_starts_with($label, '3') => '#facc15', // Жёлтый
                str_starts_with($label, '4') => '#4ade80', // Зелёный
                str_starts_with($label, '5') => '#22c55e', // Тёмно-зелёный
                default => '#a3a3a3',
            };

            $ratingColors[] = $color;
        }

        return [
            Box::make('Фильтры', [$filterForm]),

            Grid::make([
                Column::make([
                    Box::make('Отзывы по месяцам и статусам', [
                        LineChartMetric::make('Отзывы по месяцам')
                            ->line($series, $colors, 'column')
                            ->withoutSortKeys()  // сохранит порядок месяцев
                            ->columnSpan(12),
                    ]),
                ])


            ]),
            Grid::make([
                Column::make([
                    Box::make('Отзывы по оценкам', [
                        DonutChartMetric::make('Оценки')
                            ->values($formattedRatings)
                            ->colors($ratingColors)
                            ->decimals(0)
                            ->columnSpan(12),
                    ]),
                ])->columnSpan(6),
            ])
        ];

    }
}
