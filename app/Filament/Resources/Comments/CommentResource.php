<?php

namespace App\Filament\Resources\Comments;

use App\Models\Comment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        return (string) (Comment::where('status', 'pending')->count() ?: '') ?: null;
    }

    public static function table(Table $table): Table
    {
        $status = fn (string $name, string $label, string $icon, string $color) => Action::make($name)->label($label)->icon($icon)->color($color)
            ->visible(fn (Comment $r) => $r->status !== $name)
            ->action(fn (Comment $r) => $r->update(['status' => $name]));

        $bulk = fn (string $name, string $label) => BulkAction::make($name)->label($label)
            ->action(fn (Collection $records) => $records->each->update(['status' => $name]))
            ->deselectRecordsAfterCompletion();

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('content.translations'))
            ->columns([
                TextColumn::make('name')->description(fn (Comment $r) => $r->email)->searchable(['name', 'email']),
                TextColumn::make('body')->limit(120)->wrap()->searchable(),
                TextColumn::make('content.title')->label('On')->state(fn (Comment $r) => $r->content?->title)->limit(40),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success', 'pending' => 'warning', 'spam' => 'danger', default => 'gray',
                }),
                TextColumn::make('ip')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(Comment::STATUSES)->default('pending')])
            ->recordActions([
                $status('approved', 'Approve', 'heroicon-o-check', 'success'),
                $status('spam', 'Spam', 'heroicon-o-no-symbol', 'danger'),
                $status('trash', 'Trash', 'heroicon-o-trash', 'gray'),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                $bulk('approved', 'Approve'),
                $bulk('spam', 'Mark as spam'),
                $bulk('trash', 'Move to trash'),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageComments::route('/')];
    }
}
