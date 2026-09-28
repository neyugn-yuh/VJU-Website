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

        $greetingKey = match (true) {
            $hour >= 5 && $hour < 12 => 'admin.greeting_morning',
            $hour >= 12 && $hour < 18 => 'admin.greeting_afternoon',
            default => 'admin.greeting_evening',
        };

        $locale = app()->getLocale();
        $greeting = __($greetingKey);

        $draftsCount = Content::where('status', ContentStatus::Draft->value)->count();
        $pendingCount = Content::where('status', ContentStatus::PendingReview->value)->count();
        $publishedCount = Content::where('status', ContentStatus::Published->value)->count();

        return [
            'user' => $user,
            'greeting' => $greeting,
            'today' => now()->locale($locale)->isoFormat('dddd, D MMMM, YYYY'),
            'draftsCount' => $draftsCount,
            'pendingCount' => $pendingCount,
            'publishedCount' => $publishedCount,
        ];
    }
}
