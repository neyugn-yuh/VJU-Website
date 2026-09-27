<?php

namespace App\Filament\Widgets;

use App\Domain\Content\ContentType;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class PostsByAuthorChart extends ChartWidget
{
    protected ?string $heading = 'Tác giả có nhiều bài viết';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $users = User::withCount(['contents' => fn ($q) => $q->where('type', ContentType::Post->value)])
            ->orderByDesc('contents_count')->limit(6)->get()->where('contents_count', '>', 0);

        $colors = [
            '#5e6ad2',
            '#38bdf8',
            '#34d399',
            '#f59e0b',
            '#f43f5e',
            '#a78bfa',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Số bài viết',
                    'data' => $users->pluck('contents_count')->values()->all(),
                    'backgroundColor' => array_slice($colors, 0, $users->count()),
                    'borderWidth' => 2,
                    'borderColor' => 'transparent',
                ],
            ],
            'labels' => $users->pluck('name')->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '72%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 8,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 14,
                    ],
                ],
            ],
        ];
    }
}
