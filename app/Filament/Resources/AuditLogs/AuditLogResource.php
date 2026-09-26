<?php

namespace App\Filament\Resources\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,name'))
            ->columns([
                TextColumn::make('created_at')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user.name')->placeholder('System'),
                TextColumn::make('action')->badge(),
                TextColumn::make('subject_type')->label('Subject')->formatStateUsing(fn (AuditLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—'),
                TextColumn::make('ip')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')->options(fn () => AuditLog::distinct()->orderBy('action')->pluck('action', 'action')),
                SelectFilter::make('user_id')->label('User')->searchable()->options(fn () => User::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('subject_type')->label('Subject type')->options(fn () => AuditLog::whereNotNull('subject_type')->distinct()->pluck('subject_type')->mapWithKeys(fn ($t) => [$t => class_basename($t)])),
                Filter::make('date')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('details')->icon('heroicon-o-magnifying-glass')->color('gray')->modalSubmitAction(false)
                    ->modalContent(fn (AuditLog $r) => new HtmlString(
                        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:.8rem">'
                        .'<div><strong>Before</strong><pre style="white-space:pre-wrap;max-height:28rem;overflow:auto">'.e(json_encode($r->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre></div>'
                        .'<div><strong>After</strong><pre style="white-space:pre-wrap;max-height:28rem;overflow:auto">'.e(json_encode($r->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre></div></div>'
                        .'<p style="font-size:.75rem;color:#6b7280">'.e($r->user_agent).'</p>'
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
