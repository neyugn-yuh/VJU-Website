<?php

namespace App\Filament\Resources\Media\Pages;

use App\Domain\Media\MediaService;
use App\Filament\Resources\Media\MediaResource;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Bulk upload: every file goes through MediaService (MIME sniffing, checksum de-duplication). */
class UploadMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected static ?string $title = 'Upload media';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('files')
                ->label('Files')
                ->multiple()
                ->maxParallelUploads(4)
                ->storeFiles(false)
                ->acceptedFileTypes(array_keys(config('cms.media.mimes')))
                ->maxSize(config('cms.media.max_size_kb'))
                ->required()
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(MediaService::class);
        $created = $duplicates = 0;
        $errors = [];
        $last = null;

        foreach ($data['files'] as $file) {
            try {
                $last = $service->store($file, [], auth()->user());
                $service->lastWasDuplicate ? $duplicates++ : $created++;
            } catch (ValidationException $e) {
                $errors[] = $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first();
            }
        }

        $notification = Notification::make()
            ->title("{$created} uploaded".($duplicates ? ", {$duplicates} already in the library (reused)" : ''))
            ->body($errors ? implode("\n", $errors) : null)
            ->{$errors ? 'warning' : 'success'}();
        // Rejected files stay on screen until dismissed.
        ($errors ? $notification->persistent() : $notification)->send();

        if (! $last) {
            throw new Halt;
        }

        return $last;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return MediaResource::getUrl('index');
    }
}
