<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = ContactMessage::class;

    protected static string $groupKey = 'inbox';

    protected static string $labelKey = 'contact_message';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function statuses(): array
    {
        return collect(['new', 'read', 'replied', 'closed'])->mapWithKeys(fn ($s) => [$s => __('admin.inbox_status.'.$s)])->all();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ContactMessage::query()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name')->label(admin_field('name')),
            TextEntry::make('email')->label(admin_field('email'))->copyable(),
            TextEntry::make('subject')->label(admin_field('subject'))->columnSpanFull(),
            TextEntry::make('message')->label(admin_field('message'))->columnSpanFull(),
            TextEntry::make('locale')->label(admin_field('locale')),
            TextEntry::make('created_at')->label(admin_field('created_at'))->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(admin_field('name'))->description(fn (ContactMessage $m) => $m->email)->searchable(),
                TextColumn::make('subject')->label(admin_field('subject'))->limit(40)->searchable(),
                TextColumn::make('message')->label(admin_field('message'))->limit(60),
                SelectColumn::make('status')->label(admin_field('status'))->options(self::statuses())->selectablePlaceholder(false),
                TextColumn::make('created_at')->label(admin_field('created_at'))->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label(admin_field('status'))->options(self::statuses())])
            ->recordActions([ViewAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageContactMessages::route('/')];
    }
}
