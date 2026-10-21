<?php

namespace App\Actions\Flyers;

use App\Enums\FlyerStatus;
use App\Models\Flyer;

class ExpireFlyer
{
    public function handle(Flyer $flyer): Flyer
    {
        $flyer->forceFill([
            'status' => FlyerStatus::Expired,
            'expired_at' => now(),
        ])->save();

        return $flyer;
    }
}
