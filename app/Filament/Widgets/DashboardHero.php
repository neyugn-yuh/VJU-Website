<?php

namespace App\Filament\Widgets;

use App\Domain\Content\ContentStatus;
use App\Models\Content;
use Filament\Widgets\Widget;

class DashboardHero extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-hero';

    protected function getViewData(): array
    {
        $user = auth()->user();
        $hour = (int) now()->format('H');

        $greeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Chào buổi sáng',
            $hour >= 12 && $hour < 18 => 'Chào buổi chiều',
            default => 'Chào buổi tối',
        };

        $draftsCount = Content::where('status', ContentStatus::Draft->value)->count();
        $pendingCount = Content::where('status', ContentStatus::PendingReview->value)->count();
        $publishedCount = Content::where('status', ContentStatus::Published->value)->count();

        return [
            'user' => $user,
            'greeting' => $greeting,
            'today' => now()->locale('vi')->isoFormat('dddd, D MMMM, YYYY'),
            'draftsCount' => $draftsCount,
            'pendingCount' => $pendingCount,
            'publishedCount' => $publishedCount,
        ];
    }
}
