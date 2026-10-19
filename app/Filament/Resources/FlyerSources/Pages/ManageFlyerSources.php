<?php

namespace App\Filament\Resources\FlyerSources\Pages;

use App\Enums\LicenseStatus;
use App\Filament\Resources\FlyerSources\FlyerSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFlyerSources extends ManageRecords
{
    protected static string $resource = FlyerSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['license_status' => LicenseStatus::Pending->value]),
        ];
    }
}
