<?php

namespace Tests\Feature;

use App\Actions\Flyers\PublishFlyer;
use App\Actions\Flyers\RollFlyerStatuses;
use App\Enums\FlyerStatus;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FlyerLifecycleTest extends TestCase
{
    public function test_publish_schedules_future_flyers_and_publishes_current_ones(): void
    {
        $today = CarbonImmutable::parse('2026-10-19');

        $future = $this->makeFlyer(['status' => FlyerStatus::InReview, 'valid_from' => '2026-10-22', 'valid_until' => '2026-10-28']);
        $current = $this->makeFlyer(['status' => FlyerStatus::InReview, 'valid_from' => '2026-10-18', 'valid_until' => '2026-10-24', 'published_at' => null]);

        app(PublishFlyer::class)->handle($future, $today);
        app(PublishFlyer::class)->handle($current, $today);

        $this->assertSame(FlyerStatus::Scheduled, $future->refresh()->status);
        $this->assertSame(FlyerStatus::Published, $current->refresh()->status);
        $this->assertNotNull($current->published_at);
    }

    public function test_scheduler_publishes_and_expires_at_qatar_midnight(): void
    {
        $scheduled = $this->makeFlyer(['status' => FlyerStatus::Scheduled, 'valid_from' => '2026-10-20', 'valid_until' => '2026-10-26']);
        $ending = $this->makeFlyer(['status' => FlyerStatus::Published, 'valid_from' => '2026-10-13', 'valid_until' => '2026-10-19']);

        // 23:30 in Doha on the 19th: nothing changes yet.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 20:30:00', 'UTC'));
        $result = app(RollFlyerStatuses::class)->handle();
        $this->assertSame(['published' => 0, 'expired' => 0], $result);

        // 00:15 in Doha on the 20th: the new flyer goes live, the old one expires.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 21:15:00', 'UTC'));
        $result = app(RollFlyerStatuses::class)->handle();

        $this->assertSame(['published' => 1, 'expired' => 1], $result);
        $this->assertSame(FlyerStatus::Published, $scheduled->refresh()->status);
        $this->assertSame(FlyerStatus::Expired, $ending->refresh()->status);
    }

    public function test_roll_command_runs(): void
    {
        $this->artisan('flyers:roll')->assertSuccessful();
    }
}
