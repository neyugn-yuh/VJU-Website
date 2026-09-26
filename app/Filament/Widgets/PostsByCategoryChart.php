<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;

class PostsByCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Top categories';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $categories = Category::with('translations')->withCount('contents')->orderByDesc('contents_count')->limit(10)->get();

        return [
            'datasets' => [['label' => 'Items', 'data' => $categories->pluck('contents_count')->all()]],
            'labels' => $categories->map->name->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y'];
    }
}
