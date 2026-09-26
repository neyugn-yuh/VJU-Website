<?php

namespace App\Filament\Resources\Media;

use App\Domain\Media\GenerateMediaDerivatives;
use App\Models\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $modelLabel = 'media file';

    protected static ?string $pluralModelLabel = 'media library';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->title ?: ($record?->filename ?? 'media');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(1)->schema([
                Text::make(fn (?Media $record) => $record ? new HtmlString($record->isImage()
                    ? '<img src="'.e($record->derivativeUrl('web')).'" alt="" style="max-width:100%;border-radius:.5rem">'
                    : '<a href="'.e($record->url()).'" target="_blank" class="underline">'.e($record->filename).'</a>') : ''),
                Text::make(fn (?Media $record) => $record ? "{$record->mime_type} · {$record->humanSize()}".($record->width ? " · {$record->width}×{$record->height}" : '') : ''),
                Text::make(fn (?Media $record) => $record ? new HtmlString('URL: <code style="word-break:break-all">'.e($record->url()).'</code>') : ''),
            ]),
            Section::make('Metadata')->columnSpan(2)->schema([
                TextInput::make('title')->maxLength(255),
                TextInput::make('alt')->label('ALT text')->maxLength(1000)->helperText('Describe the image for screen readers. Required for accessibility on informative images.'),
                Textarea::make('caption')->rows(2),
                Textarea::make('description')->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $grid = (($table->getLivewire()->display ?? 'list') === 'grid');

        return $table
            ->defaultSort('created_at', 'desc')
            ->contentGrid($grid ? ['sm' => 2, 'md' => 4, 'xl' => 6] : null)
            ->columns($grid ? [
                Stack::make([
                    ImageColumn::make('preview')->state(fn (Media $r) => $r->isImage() ? $r->derivativeUrl('thumb') : null)
                        ->defaultImageUrl(fn (Media $r) => 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="160" height="120"><rect width="100%" height="100%" fill="#e5e7eb"/><text x="50%" y="55%" text-anchor="middle" font-family="sans-serif" font-size="20" fill="#6b7280">'.e(strtoupper(pathinfo($r->filename, PATHINFO_EXTENSION))).'</text></svg>'))
                        ->imageHeight(120)->extraImgAttributes(['style' => 'object-fit:cover;width:100%;border-radius:.375rem']),
                    TextColumn::make('filename')->searchable(['filename', 'title', 'original_filename'])->limit(28)->size('sm'),
                    TextColumn::make('size')->formatStateUsing(fn (Media $r) => $r->humanSize())->color('gray')->size('xs'),
                ]),
            ] : [
                ImageColumn::make('preview')->state(fn (Media $r) => $r->isImage() ? $r->derivativeUrl('thumb') : null)->imageSize(48),
                TextColumn::make('filename')->searchable(['filename', 'title', 'original_filename'])->description(fn (Media $r) => $r->title)->limit(50),
                TextColumn::make('mime_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('size')->formatStateUsing(fn (Media $r) => $r->humanSize())->sortable(),
                TextColumn::make('alt')->label('ALT')->placeholder('missing')->limit(30)->toggleable(),
                TextColumn::make('created_at')->label('Uploaded')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Type')->options(['image' => 'Images', 'application/pdf' => 'PDF', 'document' => 'Office documents', 'video' => 'Video'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'image' => $q->where('mime_type', 'like', 'image/%'),
                        'application/pdf' => $q->where('mime_type', 'application/pdf'),
                        'document' => $q->where('mime_type', 'like', 'application/%')->where('mime_type', '!=', 'application/pdf'),
                        'video' => $q->where('mime_type', 'like', 'video/%'),
                        default => $q,
                    }),
                Filter::make('uploaded')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
                Filter::make('missing_alt')->label('Images without ALT')
                    ->query(fn (Builder $q) => $q->where('mime_type', 'like', 'image/%')->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('regenerate')->label('Regenerate sizes')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (Media $r) => $r->isImage() && auth()->user()->can('update', $r))
                    ->action(fn (Media $r) => GenerateMediaDerivatives::dispatch($r->id)->onQueue('media')),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()->modalDescription('Deletes the file from storage. Content that embeds this file will show a broken link.'),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\UploadMedia::route('/upload'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}
