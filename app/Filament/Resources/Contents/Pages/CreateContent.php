<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Domain\Content\ContentService;
use App\Domain\Content\ContentType;
use App\Filament\Resources\Contents\ContentResource;
use App\Filament\Resources\Contents\Pages\Concerns\SavesThroughContentService;
use App\Models\Content;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

class CreateContent extends CreateRecord
{
    use SavesThroughContentService;

    protected static string $resource = ContentResource::class;

    #[Url]
    public string $type = 'post';

    public function mount(): void
    {
        $this->type = ContentType::tryFrom($this->type)?->value ?? ContentType::Post->value;

        parent::mount();
    }

    public function getTitle(): string
    {
        return 'New '.strtolower(ContentType::from($this->type)->label());
    }

    protected function handleRecordCreation(array $data): Model
    {
        return $this->throughService(fn () => app(ContentService::class)->save(new Content, [...$data, 'type' => $this->type], auth()->user()));
    }

    protected function getRedirectUrl(): string
    {
        return ContentResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
