<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'] ?? null]);
        $user->forceFill(['is_active' => (bool) ($data['is_active'] ?? true)])->save();
        $user->syncRoles($data['roles'] ?? []);

        return $user;
    }
}
