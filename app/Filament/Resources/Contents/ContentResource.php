<?php

namespace App\Filament\Resources\Contents;

use App\Domain\Content\ContentType;
use App\Filament\Resources\Contents\Pages\CreateContent;
use App\Filament\Resources\Contents\Pages\EditContent;
use App\Filament\Resources\Contents\Pages\ListContents;
use App\Filament\Resources\Contents\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Contents\Schemas\ContentForm;
use App\Filament\Resources\Contents\Tables\ContentsTable;
use App\Models\Content;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * All content types share one resource; each type is a list tab (?tab=post) with its own
 * navigation entry. Type-specific fields are toggled in ContentForm.
 */
class ContentResource extends Resource
{
    protected static ?string $model = Content::class;

    protected static ?string $modelLabel = 'content';

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->title ?? static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return ContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['translations:id,content_id,locale,title,path', 'author:id,name', 'categories.translations']);

        // Users without content.viewAny only ever see their own content (server-side, not UI).
        $user = auth()->user();
        if ($user && ! $user->can('content.viewAny')) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    public static function getNavigationItems(): array
    {
        $icons = [
            ContentType::Post->value => Heroicon::OutlinedNewspaper,
            ContentType::Page->value => Heroicon::OutlinedDocumentText,
            ContentType::Document->value => Heroicon::OutlinedArrowDownTray,
            ContentType::Notification->value => Heroicon::OutlinedBellAlert,
            ContentType::TuitionFee->value => Heroicon::OutlinedBanknotes,
            ContentType::Opportunity->value => Heroicon::OutlinedBriefcase,
        ];

        return collect(ContentType::cases())->map(fn (ContentType $type, int $i) => NavigationItem::make($type->pluralLabel())
            ->group($i < 2 ? 'Content' : 'Structured content')
            ->icon($icons[$type->value])
            ->sort($i)
            ->url(static::getUrl('index', ['tab' => $type->value]))
            ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.*')
                && (request('tab', request('type')) ?? static::currentRecordType()) === $type->value))
            ->all();
    }

    private static function currentRecordType(): ?string
    {
        $record = request()->route('record');
        if (! $record) {
            return ContentType::Post->value;
        }

        return Content::withTrashed()->whereKey($record instanceof Content ? $record->getKey() : $record)->first(['type'])?->type->value;
    }

    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContents::route('/'),
            'create' => CreateContent::route('/create'),
            'edit' => EditContent::route('/{record}/edit'),
        ];
    }
}
