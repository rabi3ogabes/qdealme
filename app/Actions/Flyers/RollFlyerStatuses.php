<?php

namespace App\Actions\Flyers;

use App\Models\City;
use App\Models\Flyer;
use Carbon\CarbonInterface;

/**
 * Runs from the scheduler: publishes scheduled flyers whose start date has
 * arrived and expires published flyers whose end date has passed. Dates are
 * compared against "today" in the launch country's timezone.
 *
 * @return array{published: int, expired: int}
 */
class RollFlyerStatuses
{
    public function __construct(
        private readonly PublishFlyer $publish,
        private readonly ExpireFlyer $expire,
    ) {}

    public function handle(?CarbonInterface $today = null): array
    {
        $today ??= local_today(City::query()->where('slug', config('qdealme.default_city'))->with('country')->first());

        $published = 0;
        $expired = 0;

        Flyer::query()->scheduledToStart($today)->with('source')->each(function (Flyer $flyer) use ($today, &$published): void {
            $this->publish->handle($flyer, $today);
            $published++;
        });

        Flyer::query()->dueToExpire($today)->each(function (Flyer $flyer) use (&$expired): void {
            $this->expire->handle($flyer);
            $expired++;
        });

        return ['published' => $published, 'expired' => $expired];
    }
}
