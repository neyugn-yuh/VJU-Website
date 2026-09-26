<?php

namespace App\Filament\Forms;

use App\Domain\Media\MediaService;
use App\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The CMS WYSIWYG (Filament/TipTap, MIT): headings, formatting, links, tables, quotes, code, images.
 * Uploaded images become Media records; "Media library" inserts existing media. Output is
 * sanitized again server-side by ContentService.
 */
class CmsRichEditor
{
    public static function make(string $name): RichEditor
    {
        $canUpload = fn () => (bool) auth()->user()?->can('media.upload');

        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['h2', 'h3', 'h4'],
                ['alignStart', 'alignCenter', 'alignEnd'],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList', 'horizontalRule'],
                ['table', 'attachFiles', 'mediaLibrary', 'details'],
                ['undo', 'redo'],
            ])
            ->fileAttachments($canUpload)
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->fileAttachmentsMaxSize(config('cms.media.max_size_kb'))
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file) => app(MediaService::class)->store($file, [], auth()->user())->id)
            ->getFileAttachmentUrlUsing(fn ($file) => Media::find($file)?->derivativeUrl('web'))
            ->tools([
                RichEditorTool::make('mediaLibrary')
                    ->label('Media library')
                    ->icon(Heroicon::Photo)
                    ->action(),
            ])
            ->registerActions([self::mediaLibraryAction()])
            ->extraInputAttributes(['style' => 'min-height: 24rem']);
    }

    private static function mediaLibraryAction(): Action
    {
        return Action::make('mediaLibrary')
            ->modalHeading('Insert from media library')
            ->schema([
                MediaPicker::make('media_id', imagesOnly: false)->label('File')->required(),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $media = Media::find($data['media_id']);
                if (! $media) {
                    return;
                }

                $node = $media->isImage()
                    ? ['type' => 'image', 'attrs' => ['src' => $media->derivativeUrl('web'), 'alt' => $media->alt ?: $media->title, 'id' => (string) $media->id]]
                    : ['type' => 'paragraph', 'content' => [[
                        'type' => 'text',
                        'text' => $media->title ?: $media->filename,
                        'marks' => [['type' => 'link', 'attrs' => ['href' => $media->url(), 'target' => '_blank']]],
                    ]]];

                $component->runCommands(
                    [EditorCommand::make('insertContent', arguments: [[$node]])],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );
            });
    }
}
