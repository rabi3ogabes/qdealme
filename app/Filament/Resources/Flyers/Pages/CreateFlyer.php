<?php

namespace App\Filament\Resources\Flyers\Pages;

use App\Enums\FlyerStatus;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Jobs\ProcessFlyerUpload;
use Filament\Resources\Pages\CreateRecord;

class CreateFlyer extends CreateRecord
{
    protected static string $resource = FlyerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = FlyerStatus::Draft->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        ProcessFlyerUpload::dispatch($this->record->id);
    }
}
