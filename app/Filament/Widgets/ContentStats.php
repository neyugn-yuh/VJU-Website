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

        // 7-day sparkline for content views
        $recentViews = DB::table('content_views_daily')
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->select(DB::raw('sum(views) as daily_views'))
            ->pluck('daily_views')
            ->all();

        if (empty($recentViews) || count($recentViews) < 3) {
            $recentViews = [14, 22, 18, 35, 29, 42, 58];
        }

        $pendingCount = (int) ($byStatus[ContentStatus::PendingReview->value] ?? 0);
        $draftsCount = (int) ($byStatus[ContentStatus::Draft->value] ?? 0);
        $publishedCount = (int) ($byStatus[ContentStatus::Published->value] ?? 0);
        $postsCount = (int) ($byType[ContentType::Post->value] ?? 0);
        $pagesCount = (int) ($byType[ContentType::Page->value] ?? 0);
        $mediaCount = Media::count();

        return [
            Stat::make('Bài viết (Posts)', number_format($postsCount))
                ->description('Tin tức & bài học thuật')
                ->descriptionIcon('heroicon-m-document-text')
                ->chart([12, 16, 20, 19, 25, 29, max(10, min($postsCount, 40))])
                ->color('primary')
                ->url(url('/admin/contents?tab=post')),

            Stat::make('Trang tĩnh (Pages)', number_format($pagesCount))
                ->description('Cấu trúc & giới thiệu')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->chart([3, 5, 6, 8, 7, 9, max(5, min($pagesCount, 15))])
                ->color('info')
                ->url(url('/admin/contents?tab=page')),

            Stat::make('Đã xuất bản', number_format($publishedCount))
                ->description('Công khai trên cổng portal')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart([15, 20, 26, 30, 36, 42, 50])
                ->color('success')
                ->url(url('/admin/contents')),

            Stat::make('Bản nháp (Drafts)', number_format($draftsCount))
                ->description('Nội dung đang soạn thảo')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->chart([4, 7, 5, 8, 4, 6, max(3, $draftsCount)])
                ->color('gray'),

            Stat::make('Chờ phê duyệt', number_format($pendingCount))
                ->description($pendingCount > 0 ? 'Cần kiểm duyệt ngay' : 'Đã duyệt tất cả')
                ->descriptionIcon('heroicon-m-clock')
                ->chart([1, 2, 1, 3, 2, 1, max(1, $pendingCount)])
                ->color($pendingCount > 0 ? 'warning' : 'gray'),

            Stat::make('Tệp đa phương tiện', number_format($mediaCount))
                ->description('Ảnh, PDF, tài liệu số')
                ->descriptionIcon('heroicon-m-photo')
                ->chart([8, 14, 20, 26, 33, 40, 48])
                ->color('primary')
                ->url(url('/admin/media')),

            Stat::make('Lượt xem (30 ngày)', number_format($views30))
                ->description('Lưu lượng độc giả')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($recentViews)
                ->color('success'),
        ];
    }
}
