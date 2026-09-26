<?php

namespace App\Filament\Widgets;

use App\Domain\Content\ContentStatus;
use App\Domain\Content\ContentType;
use App\Models\Content;
use App\Models\Media;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ContentStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $byType = Content::select('type', DB::raw('count(*) as c'))->groupBy('type')->pluck('c', 'type');
        $byStatus = Content::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $views30 = (int) DB::table('content_views_daily')->where('date', '>=', now()->subDays(30)->toDateString())->sum('views');

        return [
            Stat::make('Posts', number_format($byType[ContentType::Post->value] ?? 0)),
            Stat::make('Pages', number_format($byType[ContentType::Page->value] ?? 0)),
            Stat::make('Published', number_format($byStatus[ContentStatus::Published->value] ?? 0))->color('success'),
            Stat::make('Drafts', number_format($byStatus[ContentStatus::Draft->value] ?? 0)),
            Stat::make('Pending review', number_format($byStatus[ContentStatus::PendingReview->value] ?? 0))->color('warning'),
            Stat::make('Media files', number_format(Media::count())),
            Stat::make('Views (30 days)', number_format($views30)),
        ];
    }
}
