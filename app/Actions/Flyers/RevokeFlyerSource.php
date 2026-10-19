<?php

namespace App\Actions\Flyers;

use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Models\FlyerSource;

/**
 * When a retailer withdraws permission, every flyer that came from the
 * source is taken offline immediately.
 */
class RevokeFlyerSource
{
    public function handle(FlyerSource $source, ?string $notes = null): int
    {
        $source->forceFill([
            'license_status' => LicenseStatus::Revoked,
            'notes' => trim(($source->notes ? $source->notes."\n" : '').($notes ?? '')) ?: $source->notes,
        ])->save();

        return $source->flyers()
            ->whereNotIn('status', [FlyerStatus::Archived->value])
            ->update(['status' => FlyerStatus::Archived->value]);
    }
}
