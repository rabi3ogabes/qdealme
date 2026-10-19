<?php

namespace App\Filament\Resources\Offers;

use App\Enums\Verification;
use App\Models\Category;
use App\Models\Offer;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Collection;

/**
 * Shared form, columns and actions for offers, used both by the standalone
 * review queue and by the offers tab on each flyer.
 */
final class OfferSchema
{
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::components());
    }

    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            TextInput::make('title_ar')->label(admin_field('title_ar'))->required()->maxLength(160)->extraInputAttributes(['dir' => 'rtl']),
            TextInput::make('title_en')->label(admin_field('title_en'))->required()->maxLength(160),
            TextInput::make('brand')->label(admin_field('brand'))->maxLength(96),
            Select::make('category_id')->label(admin_field('category'))
                ->options(fn () => Category::query()->whereNotNull('parent_id')->orWhereDoesntHave('children')->orderBy('name_en')->pluck('name_en', 'id'))
                ->searchable(),
            TextInput::make('price')->label(admin_field('price'))->numeric()->minValue(0.01)->required()->prefix('QAR'),
            TextInput::make('old_price')->label(admin_field('old_price'))->numeric()->minValue(0)->prefix('QAR')->gt('price'),
            TextInput::make('size_value')->label(admin_field('size_value'))->numeric()->minValue(0),
            Select::make('size_unit')->label(admin_field('size_unit'))->options(['g' => 'g', 'kg' => 'kg', 'ml' => 'ml', 'l' => 'L', 'pc' => 'pc']),
            Select::make('verification')->label(admin_field('verification'))->options(Verification::class)->required()->native(false),
            TextInput::make('raw_title')->label(admin_field('raw_title'))->disabled()->dehydrated(false),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function columns(bool $withFlyer = true): array
    {
        return array_values(array_filter([
            TextColumn::make('title_en')->label(admin_field('title_en'))->searchable()->description(fn (Offer $o) => $o->title_ar)->limit(40),
            $withFlyer ? TextColumn::make('retailer.name_en')->label(admin_field('retailer'))->sortable() : null,
            TextColumn::make('page.number')->label(admin_field('page')),
            TextColumn::make('price')->label(admin_field('price'))->money('QAR')->sortable(),
            TextColumn::make('old_price')->label(admin_field('old_price'))->money('QAR')->toggleable(),
            TextColumn::make('unit_price')->label(admin_field('unit_price'))->formatStateUsing(fn ($state, Offer $o) => $state ? number_format((float) $state, 2).' / '.$o->unit : '—')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('category.name_en')->label(admin_field('category'))->toggleable(),
            TextColumn::make('confidence')->label(admin_field('confidence'))->formatStateUsing(fn ($state) => $state !== null ? round($state * 100).'%' : '—')->sortable(),
            TextColumn::make('verification')->label(admin_field('verification'))->badge()->sortable(),
        ]));
    }

    public static function verificationFilter(): SelectFilter
    {
        return SelectFilter::make('verification')->label(admin_field('verification'))->options(Verification::class);
    }

    public static function verifyAction(): Action
    {
        return Action::make('verify')->label(__('admin.verify'))->icon(Heroicon::OutlinedCheckBadge)->color('success')
            ->visible(fn (Offer $o) => $o->verification !== Verification::Verified)
            ->action(fn (Offer $o) => self::mark(collect([$o]), Verification::Verified));
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')->label(__('admin.reject'))->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->visible(fn (Offer $o) => $o->verification !== Verification::Rejected)
            ->action(fn (Offer $o) => self::mark(collect([$o]), Verification::Rejected));
    }

    public static function bulkVerify(): BulkAction
    {
        return BulkAction::make('verify')->label(__('admin.verify'))->icon(Heroicon::OutlinedCheckBadge)->color('success')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::mark($records, Verification::Verified));
    }

    public static function bulkReject(): BulkAction
    {
        return BulkAction::make('reject')->label(__('admin.reject'))->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::mark($records, Verification::Rejected));
    }

    /**
     * Sanity rules applied before anything is marked verified: a positive
     * price, and an old price (when present) higher than the new one.
     */
    private static function mark(Collection $offers, Verification $state): void
    {
        foreach ($offers as $offer) {
            if ($state === Verification::Verified && ((float) $offer->price <= 0 || ($offer->old_price !== null && (float) $offer->old_price <= (float) $offer->price))) {
                continue;
            }

            $offer->forceFill([
                'verification' => $state,
                'verified_by' => $state === Verification::Verified ? auth()->id() : null,
                'verified_at' => $state === Verification::Verified ? now() : null,
            ])->save();
        }
    }
}
