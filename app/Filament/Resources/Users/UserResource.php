<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true)
                ->helperText('Must match the Google account used to sign in.'),
            Select::make('roles')->multiple()->options(fn () => Role::pluck('name', 'name'))
                ->helperText('Leave empty to block CMS access without suspending.'),
            Toggle::make('is_active')->label('Active')->default(true)
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                ->helperText('Suspended users cannot sign in.'),
            TextInput::make('password')->password()->revealable()->minLength(12)->maxLength(255)
                ->dehydrated(fn ($state) => filled($state))
                ->visible(fn () => config('cms.auth.password_login'))
                ->helperText('Only used when password login is enabled (local/dev).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $r) => $r->email),
                TextColumn::make('roles.name')->badge(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                IconColumn::make('google_subject')->label('Google linked')->boolean()->state(fn (User $r) => filled($r->google_subject)),
                TextColumn::make('last_login_at')->since()->sortable()->placeholder('never'),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (User $r) => $r->is_active ? 'Suspend' : 'Activate')
                    ->icon(fn (User $r) => $r->is_active ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (User $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->hidden(fn (User $r) => $r->is(auth()->user()))
                    ->action(fn (User $r) => $r->forceFill(['is_active' => ! $r->is_active])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
