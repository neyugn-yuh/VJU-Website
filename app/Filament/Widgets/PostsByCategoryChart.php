<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;

class PostsByCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Chuyên mục bài viết hàng đầu';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $categories = Category::with('translations')->withCount('contents')->orderByDesc('contents_count')->limit(8)->get();

        $colors = [
            '#5e6ad2',
            '#717de8',
            '#38bdf8',
            '#34d399',
            '#f59e0b',
            '#f43f5e',
            '#a78bfa',
            '#6366f1',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Số lượng nội dung',
                    'data' => $categories->pluck('contents_count')->all(),
                    'backgroundColor' => array_slice($colors, 0, $categories->count()),
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $categories->map(fn ($c) => $c->name ?? 'Không tên')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                    'grid' => [
                        'color' => 'rgba(128, 128, 128, 0.08)',
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
