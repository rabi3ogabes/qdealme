<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Enums\Verification;
use App\Filament\Resources\Offers\OfferResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageOffers extends ManageRecords
{
    protected static string $resource = OfferResource::class;

    public function getTabs(): array
    {
        $tabs = [];

        foreach (Verification::cases() as $state) {
            $tabs[$state->value] = Tab::make($state->label())->modifyQueryUsing(fn (Builder $query) => $query->where('verification', $state->value));
        }

        return $tabs + ['all' => Tab::make(__('ui.all'))];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Verification::Unverified->value;
    }
}
