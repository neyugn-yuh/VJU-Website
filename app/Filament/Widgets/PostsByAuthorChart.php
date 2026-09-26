<?php

namespace App\Filament\Widgets;

use App\Domain\Content\ContentType;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class PostsByAuthorChart extends ChartWidget
{
    protected ?string $heading = 'Posts by author';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $users = User::withCount(['contents' => fn ($q) => $q->where('type', ContentType::Post->value)])
            ->orderByDesc('contents_count')->limit(10)->get()->where('contents_count', '>', 0);

        return [
            'datasets' => [['label' => 'Posts', 'data' => $users->pluck('contents_count')->values()->all()]],
            'labels' => $users->pluck('name')->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
