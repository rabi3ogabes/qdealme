<?php

namespace App\Filament\Resources\Offers;

use App\Enums\Verification;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Models\Offer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = Offer::class;

    protected static string $groupKey = 'catalog';

    protected static string $labelKey = 'offer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title_en';

    public static function getNavigationBadge(): ?string
    {
        $count = Offer::query()->where('verification', Verification::Unverified->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return OfferSchema::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['retailer', 'page', 'category']))
            ->defaultSort('created_at', 'desc')
            ->columns(OfferSchema::columns())
            ->filters([
                OfferSchema::verificationFilter(),
                SelectFilter::make('retailer_id')->label(admin_field('retailer'))->relationship('retailer', 'name_en')->searchable()->preload(),
            ])
            ->recordActions([OfferSchema::verifyAction(), OfferSchema::rejectAction(), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([OfferSchema::bulkVerify(), OfferSchema::bulkReject()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOffers::route('/')];
    }
}
