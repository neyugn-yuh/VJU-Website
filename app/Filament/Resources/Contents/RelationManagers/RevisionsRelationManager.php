<?php

namespace App\Filament\Resources\Contents\RelationManagers;

use App\Domain\Content\ContentService;
use App\Models\ContentRevision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revision history';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->label('#'),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('note')->placeholder('—')->wrap(),
                TextColumn::make('created_at')->dateTime('d/m/Y H:i:s'),
            ])
            ->recordActions([
                Action::make('inspect')->label('View')->icon('heroicon-o-eye')->color('gray')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (ContentRevision $record) => new HtmlString(self::renderSnapshot($record))),
                Action::make('restore')->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalDescription('The current state is kept as a new revision before restoring, so nothing is lost.')
                    ->visible(fn () => auth()->user()->can('update', $this->getOwnerRecord()))
                    ->action(function (ContentRevision $record) {
                        app(ContentService::class)->restoreRevision($this->getOwnerRecord(), $record, auth()->user());
                        Notification::make()->success()->title("Version {$record->version} restored")->send();
                        $this->redirect(request()->header('Referer') ?? url()->current());
                    }),
            ]);
    }

    private static function renderSnapshot(ContentRevision $revision): string
    {
        $html = '';
        foreach ($revision->snapshot['translations'] ?? [] as $locale => $t) {
            $html .= '<div style="margin-bottom:1rem"><strong>'.e(strtoupper($locale)).': '.e($t['title'] ?? '').'</strong>'
                .'<div style="color:#6b7280;font-size:.85rem">/'.e($t['slug'] ?? '').'</div>'
                .'<div style="max-height:18rem;overflow:auto;border:1px solid #e5e7eb;padding:.5rem;margin-top:.25rem;font-size:.85rem">'
                .e(mb_substr(strip_tags((string) ($t['body'] ?? '')), 0, 3000)).'</div></div>';
        }

        return $html ?: '<em>Empty snapshot</em>';
    }
}
