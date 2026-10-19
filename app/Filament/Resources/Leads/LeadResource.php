<?php

namespace App\Filament\Resources\Leads;

use App\Enums\RetailerType;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Leads\Pages\ManageLeads;
use App\Models\Lead;
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

class LeadResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = Lead::class;

    protected static string $groupKey = 'inbox';

    protected static string $labelKey = 'lead';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function statuses(): array
    {
        return collect(['new', 'contacted', 'converted', 'closed'])->mapWithKeys(fn ($s) => [$s => __('admin.inbox_status.'.$s)])->all();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Lead::query()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('company_name')->label(admin_field('company_name')),
            TextEntry::make('contact_name')->label(admin_field('contact_name')),
            TextEntry::make('email')->label(admin_field('email'))->copyable(),
            TextEntry::make('phone')->label(admin_field('phone'))->copyable(),
            TextEntry::make('city.name_en')->label(admin_field('city')),
            TextEntry::make('retailer_type')->label(admin_field('type'))->formatStateUsing(fn (?string $state) => $state ? RetailerType::from($state)->label() : '—'),
            TextEntry::make('message')->label(admin_field('message'))->columnSpanFull(),
            TextEntry::make('created_at')->label(admin_field('created_at'))->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company_name')->label(admin_field('company_name'))->searchable(),
                TextColumn::make('contact_name')->label(admin_field('contact_name'))->description(fn (Lead $l) => $l->email)->searchable(),
                TextColumn::make('phone')->label(admin_field('phone')),
                TextColumn::make('city.name_en')->label(admin_field('city')),
                SelectColumn::make('status')->label(admin_field('status'))->options(self::statuses())->selectablePlaceholder(false),
                TextColumn::make('created_at')->label(admin_field('created_at'))->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label(admin_field('status'))->options(self::statuses())])
            ->recordActions([ViewAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeads::route('/')];
    }
}
