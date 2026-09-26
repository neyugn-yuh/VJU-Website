<?php

namespace App\Filament\Resources\Contents\Tables;

use App\Domain\Content\ContentService;
use App\Domain\Content\ContentStatus;
use App\Models\Category;
use App\Models\Content;
use App\Models\User;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->state(fn (Content $record) => $record->title)
                    ->description(fn (Content $record) => $record->translations->pluck('locale')->map(fn ($l) => strtoupper($l))->implode(' · '))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('translations', fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
                    ->wrap()
                    ->limit(90),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ContentStatus $state) => $state->label())
                    ->color(fn (ContentStatus $state) => $state->color())
                    ->sortable(),
                TextColumn::make('author.name')->label('Author')->toggleable(),
                TextColumn::make('categories')->label('Categories')
                    ->state(fn (Content $record) => $record->categories->map->name->all())
                    ->badge()->limitList(2)->toggleable(),
                TextColumn::make('published_at')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('view_count')->label('Views')->numeric()->sortable()->toggleable(),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContentStatus::options()),
                SelectFilter::make('category')->label('Category')->multiple()
                    ->options(fn () => Category::with('translations')->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->name]))
                    ->query(fn (Builder $q, array $data) => $q->when($data['values'] ?? null, fn ($q, $ids) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids)))),
                SelectFilter::make('author_id')->label('Author')->searchable()
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('locale')->label('Has translation')->options(Locales::options())
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn ($q, $l) => $q->whereHas('translations', fn ($t) => $t->where('locale', $l)))),
                SelectFilter::make('missing_locale')->label('Missing translation')->options(Locales::options())
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn ($q, $l) => $q->whereDoesntHave('translations', fn ($t) => $t->where('locale', $l)))),
                Filter::make('published_between')->schema([
                    DatePicker::make('from'),
                    DatePicker::make('until'),
                ])->query(fn (Builder $q, array $data) => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('published_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('published_at', '<=', $d))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('view')->label('View on site')->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Content $record) => $record->url(), shouldOpenInNewTab: true)
                        ->visible(fn (Content $record) => $record->isPublished()),
                    Action::make('preview')->icon('heroicon-o-eye')
                        ->url(fn (Content $record) => route('preview', $record), shouldOpenInNewTab: true)
                        ->visible(fn (Content $record) => ! $record->isPublished() && ! $record->trashed()),
                    DeleteAction::make()->label('Move to trash'),
                    RestoreAction::make(),
                    ForceDeleteAction::make()->modalDescription('This permanently deletes the content, its translations, SEO data, revisions and comments. This cannot be undone.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkStatus('publish', 'Publish', ContentStatus::Published, 'heroicon-o-check-circle'),
                    self::bulkStatus('unpublish', 'Revert to draft', ContentStatus::Draft, 'heroicon-o-arrow-uturn-left'),
                    BulkAction::make('assign_category')->label('Assign category')->icon('heroicon-o-folder-plus')
                        ->schema([Select::make('category_id')->label('Category')->required()->searchable()
                            ->options(fn () => Category::with('translations')->get()->mapWithKeys(fn (Category $c) => [$c->id => $c->name]))])
                        ->action(fn (Collection $records, array $data) => self::eachAuthorized($records, 'update', function (Content $c) use ($data) {
                            app(ContentService::class)->save($c, ['categories' => [...$c->categories->pluck('id'), $data['category_id']]], auth()->user());
                        }))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assign_author')->label('Assign author')->icon('heroicon-o-user')
                        ->visible(fn () => Gate::allows('assignAuthor', Content::class))
                        ->schema([Select::make('author_id')->label('Author')->required()->searchable()->options(fn () => User::orderBy('name')->pluck('name', 'id'))])
                        ->action(fn (Collection $records, array $data) => self::eachAuthorized($records, 'update', function (Content $c) use ($data) {
                            app(ContentService::class)->save($c, ['author_id' => (int) $data['author_id']], auth()->user());
                        }))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()->label('Move to trash'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function bulkStatus(string $name, string $label, ContentStatus $status, string $icon): BulkAction
    {
        return BulkAction::make($name)->label($label)->icon($icon)->requiresConfirmation()
            ->action(fn (Collection $records) => self::eachAuthorized($records, 'update', fn (Content $c) => app(ContentService::class)->transition($c, $status, auth()->user())))
            ->deselectRecordsAfterCompletion();
    }

    /** Applies $callback per record; each record goes through the policy and the service, failures are reported, not fatal. */
    private static function eachAuthorized(Collection $records, string $ability, callable $callback): void
    {
        $done = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                if (Gate::denies($ability, $record)) {
                    $failed++;

                    continue;
                }
                $callback($record);
                $done++;
            } catch (Throwable) {
                $failed++;
            }
        }

        Notification::make()
            ->title("{$done} updated".($failed ? ", {$failed} skipped (not allowed or invalid)" : ''))
            ->{$failed ? 'warning' : 'success'}()
            ->send();
    }
}
