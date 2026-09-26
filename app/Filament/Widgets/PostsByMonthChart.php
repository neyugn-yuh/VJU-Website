<?php

namespace App\Filament\Widgets;

use App\Domain\Content\ContentType;
use App\Models\Content;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PostsByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Posts published per month';

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
            'datasets' => [['label' => 'Posts', 'data' => $months->map(fn ($m) => $rows[$m] ?? 0)->all()]],
            'labels' => $months->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
