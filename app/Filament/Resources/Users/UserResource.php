<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = User::class;

    protected static string $groupKey = 'people';

    protected static string $labelKey = 'user';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record->getKey() !== auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(admin_field('name'))->required()->maxLength(120),
            TextInput::make('email')->label(admin_field('email'))->email()->required()->maxLength(190)->unique(ignoreRecord: true),
            Select::make('role')->label(admin_field('role'))->options(UserRole::class)->required()->native(false),
            Select::make('locale')->label(admin_field('locale'))->options(['ar' => 'العربية', 'en' => 'English'])->required()->default('ar'),
            TextInput::make('password')->label(admin_field('password'))->password()->revealable()
                ->required(fn (?User $record) => $record === null)
                ->dehydrated(fn (?string $state) => filled($state))
                ->minLength(10)
                ->helperText(admin_field('new_password_help')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(admin_field('name'))->description(fn (User $u) => $u->email)->searchable(['name', 'email']),
                TextColumn::make('role')->label(admin_field('role'))->badge(),
                IconColumn::make('app_authentication_secret')->label('2FA')->boolean()
                    ->state(fn (User $u) => filled($u->app_authentication_secret)),
                TextColumn::make('created_at')->label(admin_field('created_at'))->date()->sortable(),
            ])
            ->filters([SelectFilter::make('role')->label(admin_field('role'))->options(UserRole::class)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
