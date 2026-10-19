<?php

namespace App\Actions\Flyers;

use App\Enums\FlyerStatus;
use App\Exceptions\UnlicensedSourceException;
use App\Models\Flyer;
use Carbon\CarbonInterface;

/**
 * Moves a reviewed flyer to "scheduled" or "published" depending on whether
 * its validity window has started in the shopper's timezone.
 */
class PublishFlyer
{
    public function handle(Flyer $flyer, ?CarbonInterface $today = null): Flyer
    {
        $flyer->loadMissing('source');

        if ($flyer->source === null || ! $flyer->source->isApproved()) {
            throw UnlicensedSourceException::forSource($flyer->flyer_source_id);
        }

        $today ??= local_today();
        $countdown = $flyer->countdown($today);

        if ($countdown->isExpired()) {
            $flyer->forceFill([
                'status' => FlyerStatus::Expired,
                'expired_at' => now(),
                'published_at' => $flyer->published_at ?? now(),
            ])->save();

            return $flyer;
        }

        if ($countdown->isUpcoming()) {
            $flyer->forceFill(['status' => FlyerStatus::Scheduled])->save();

            return $flyer;
        }

        $flyer->forceFill([
            'status' => FlyerStatus::Published,
            'published_at' => $flyer->published_at ?? now(),
            'expired_at' => null,
        ])->save();

        return $flyer;
    }
}
