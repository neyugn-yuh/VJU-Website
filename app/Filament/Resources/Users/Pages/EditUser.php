<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'roles' => $this->record->roles->pluck('name')->all()];
    }

    /** Roles go through syncRoles() so role changes are audited; nobody can lock themselves out. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $record->fill(collect($data)->only(['name', 'email', 'password'])->filter(fn ($v, $k) => $k !== 'password' || filled($v))->all());

        if (! $record->is(auth()->user())) {
            $record->forceFill(['is_active' => (bool) ($data['is_active'] ?? $record->is_active)]);
            $record->syncRoles($data['roles'] ?? []);
        }

        $record->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
