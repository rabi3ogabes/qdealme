<?php

namespace App\Filament\Resources\Flyers\Pages;

use App\Enums\FlyerStatus;
use App\Filament\Resources\Flyers\FlyerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFlyers extends ListRecords
{
    protected static string $resource = FlyerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make(__('ui.all'))];

        foreach ([FlyerStatus::InReview, FlyerStatus::Published, FlyerStatus::Scheduled, FlyerStatus::Processing, FlyerStatus::Failed, FlyerStatus::Expired] as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
