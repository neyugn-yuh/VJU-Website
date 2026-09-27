<?php

namespace App\Filament\Widgets;

use App\Domain\Content\ContentType;
use App\Models\Content;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PostsByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Bài viết xuất bản theo tháng';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $rows = Content::where('type', ContentType::Post->value)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(published_at, '%Y-%m') as month"), DB::raw('count(*) as c'))
            ->groupBy('month')->pluck('c', 'month');

        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

        return [
            'datasets' => [
                [
                    'label' => 'Bài viết mới',
                    'data' => $months->map(fn ($m) => (int) ($rows[$m] ?? 0))->all(),
                    'fill' => true,
                    'backgroundColor' => 'rgba(94, 106, 210, 0.12)',
                    'borderColor' => '#5e6ad2',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'pointBackgroundColor' => '#5e6ad2',
                    'pointBorderColor' => '#ffffff',
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $months->map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('m/Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                    'grid' => [
                        'color' => 'rgba(128, 128, 128, 0.08)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
