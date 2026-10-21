<?php

namespace App\Filament\Resources\Flyers\Pages;

use App\Filament\Resources\Flyers\FlyerResource;
use App\Jobs\ProcessFlyerUpload;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFlyer extends EditRecord
{
    protected static string $resource = FlyerResource::class;

    /** @var array<int, string> */
    private array $originalFiles = [];

    protected function getHeaderActions(): array
    {
        return [
            FlyerResource::approveAction(),
            FlyerResource::reprocessAction(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalFiles = $this->getRecord()->getAttribute('upload_files') ?? [];
    }

    protected function afterSave(): void
    {
        // New or reordered files mean the pages must be rebuilt.
        $record = $this->getRecord();

        if (($record->getAttribute('upload_files') ?? []) !== $this->originalFiles) {
            ProcessFlyerUpload::dispatch($record->getKey());
        }
    }
}
