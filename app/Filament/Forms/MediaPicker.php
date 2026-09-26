<?php

namespace App\Filament\Forms;

use App\Domain\Media\MediaService;
use App\Models\Media;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

/** Searchable media-library select with inline upload (through MediaService). */
class MediaPicker
{
    public static function make(string $name, bool $imagesOnly = true): Select
    {
        $query = fn () => Media::query()->when($imagesOnly, fn ($q) => $q->where('mime_type', 'like', 'image/%'));

        return Select::make($name)
            ->searchable()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search) => $query()
                ->where(fn ($q) => $q->where('filename', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"))
                ->latest()->limit(30)->get()
                ->mapWithKeys(fn (Media $m) => [$m->id => self::label($m)]))
            ->getOptionLabelUsing(fn ($value) => ($m = Media::find($value)) ? self::label($m) : null)
            ->options(fn () => $query()->latest()->limit(15)->get()->mapWithKeys(fn (Media $m) => [$m->id => self::label($m)]))
            ->createOptionForm([
                FileUpload::make('file')->required()->storeFiles(false)
                    ->acceptedFileTypes($imagesOnly ? ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'] : array_keys(config('cms.media.mimes')))
                    ->maxSize(config('cms.media.max_size_kb')),
                TextInput::make('alt')->label('ALT text')->maxLength(1000),
            ])
            ->createOptionUsing(fn (array $data) => app(MediaService::class)->store($data['file'], ['alt' => $data['alt'] ?? null], auth()->user())->id)
            ->createOptionAction(fn ($action) => $action->visible(fn () => auth()->user()?->can('media.upload')));
    }

    private static function label(Media $media): string
    {
        $name = e($media->title ?: $media->filename);

        if (! $media->isImage()) {
            return "<span>📄 {$name}</span>";
        }

        return new HtmlString('<span style="display:flex;gap:.5rem;align-items:center"><img src="'.e($media->derivativeUrl('thumb')).'" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px"> '.$name.'</span>');
    }
}
