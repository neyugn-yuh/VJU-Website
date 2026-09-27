<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('audit.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nhật ký hoạt động gần đây (Audit Log)')
            ->description('Theo dõi các tác vụ xuất bản, cập nhật và quản trị hệ thống theo thời gian thực')
            ->query(AuditLog::query()->with('user:id,name')->latest('id'))
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->since()
                    ->sortable()
                    ->icon('heroicon-m-clock')
                    ->color('gray'),
                TextColumn::make('user.name')
                    ->label('Người thực hiện')
                    ->placeholder('Hệ thống')
                    ->icon('heroicon-m-user')
                    ->weight('medium'),
                TextColumn::make('action')
                    ->label('Hành động')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'created', 'create', 'published' => 'success',
                        'updated', 'update', 'autosave' => 'info',
                        'deleted', 'delete' => 'danger',
                        'login' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('subject_type')
                    ->label('Đối tượng')
                    ->formatStateUsing(fn (AuditLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—')
                    ->icon('heroicon-m-cube')
                    ->color('gray'),
            ]);
    }
}
