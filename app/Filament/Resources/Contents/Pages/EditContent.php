<?php

namespace App\Filament\Resources\Contents\Pages;

use App\Domain\Content\ContentService;
use App\Filament\Resources\Contents\ContentResource;
use App\Filament\Resources\Contents\Pages\Concerns\SavesThroughContentService;
use App\Models\Content;
use App\Models\ContentDraft;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * @property Content $record
 */
class EditContent extends EditRecord
{
    use SavesThroughContentService;

    protected static string $resource = ContentResource::class;

    public ?string $autosavedAt = null;

    public ?string $autosaveHash = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->autosaveHash = $this->hashState();

        if ($draft = $this->pendingDraft()) {
            Notification::make()->warning()
                ->title('Unsaved autosave found')
                ->body('You have changes autosaved '.$draft->updated_at->diffForHumans().' that were never saved. Use "Restore autosave" to load them.')
                ->persistent()->send();
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        $status = $this->autosavedAt
            ? 'Autosaved '.Carbon::parse($this->autosavedAt)->diffForHumans()
            : 'Autosave is on';

        return new HtmlString('<span wire:poll.20s="autosave" class="text-sm text-gray-500">'.e($status).'</span>');
    }

    /** Called by wire:poll every 20 seconds; stores the raw form state apart from the record. */
    public function autosave(): void
    {
        if (! auth()->user()?->can('update', $this->record)) {
            return;
        }

        $hash = $this->hashState();
        if ($hash === $this->autosaveHash) {
            return;
        }

        ContentDraft::updateOrCreate(
            ['content_id' => $this->record->id, 'user_id' => auth()->id()],
            ['payload' => $this->data],
        );

        $this->autosaveHash = $hash;
        $this->autosavedAt = now()->toIso8601String();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return app(ContentService::class)->snapshot($this->record, withState: true);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $saved = $this->throughService(fn () => app(ContentService::class)->save($record, $data, auth()->user()));
        $this->autosaveHash = $this->hashState();
        $this->autosavedAt = null;

        return $saved;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restoreAutosave')->label('Restore autosave')->icon('heroicon-o-arrow-path')->color('warning')
                ->visible(fn () => $this->pendingDraft() !== null)
                ->requiresConfirmation()
                ->modalDescription('Replace the form with your last autosaved state? Nothing is saved until you click Save.')
                ->action(function () {
                    $this->data = $this->pendingDraft()->payload;
                    Notification::make()->success()->title('Autosave loaded. Review and save.')->send();
                }),
            Action::make('view')->label('View')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => $this->record->isPublished() ? $this->record->url() : route('preview', $this->record), shouldOpenInNewTab: true),
            DeleteAction::make()->label('Move to trash'),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    private function pendingDraft(): ?ContentDraft
    {
        return ContentDraft::where('content_id', $this->record->id)->where('user_id', auth()->id())
            ->where('updated_at', '>', $this->record->updated_at)
            ->first();
    }

    private function hashState(): string
    {
        return md5(json_encode($this->data));
    }
}
