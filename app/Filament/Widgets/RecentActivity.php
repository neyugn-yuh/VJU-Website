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
            ->query(AuditLog::query()->with('user:id,name')->latest('id'))
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('created_at')->since(),
                TextColumn::make('user.name')->placeholder('System'),
                TextColumn::make('action')->badge(),
                TextColumn::make('subject_type')->label('Subject')->formatStateUsing(fn (AuditLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—'),
            ]);
    }
}
