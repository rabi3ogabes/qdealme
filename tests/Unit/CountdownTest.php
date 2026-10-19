<?php

namespace Tests\Unit;

use App\Support\Countdown;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CountdownTest extends TestCase
{
    protected bool $seedLocations = false;

    public static function cases(): array
    {
        return [
            'ends today' => ['2026-10-19', '2026-10-15', '2026-10-19', 'active', 0, 'Ends today'],
            'ends tomorrow' => ['2026-10-19', '2026-10-15', '2026-10-20', 'active', 1, 'Ends tomorrow'],
            'several days' => ['2026-10-19', '2026-10-15', '2026-10-26', 'active', 7, '7 days left'],
            'starts tomorrow' => ['2026-10-19', '2026-10-20', '2026-10-27', 'upcoming', 1, 'Starts tomorrow'],
            'starts later' => ['2026-10-19', '2026-10-23', '2026-10-27', 'upcoming', 4, 'Starts in 4 days'],
            'expired' => ['2026-10-19', '2026-10-10', '2026-10-18', 'expired', 1, 'Expired'],
        ];
    }

    #[DataProvider('cases')]
    public function test_labels(string $today, string $from, string $until, string $state, int $days, string $label): void
    {
        app()->setLocale('en');

        $c = Countdown::for(CarbonImmutable::parse($from), CarbonImmutable::parse($until), CarbonImmutable::parse($today));

        $this->assertSame($state, $c->state);
        $this->assertSame($days, $c->days);
        $this->assertSame($label, $c->label());
    }

    public function test_it_uses_qatar_local_date_across_midnight(): void
    {
        app()->setLocale('en');

        // 20:59 UTC on the 19th is 23:59 in Doha: still the 19th, the flyer ends today.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 20:59:00', 'UTC'));
        $this->assertSame('Ends today', Countdown::for(CarbonImmutable::parse('2026-10-15'), CarbonImmutable::parse('2026-10-19'))->label());

        // 21:00 UTC is midnight in Doha: the flyer has now expired.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 21:00:00', 'UTC'));
        $this->assertTrue(Countdown::for(CarbonImmutable::parse('2026-10-15'), CarbonImmutable::parse('2026-10-19'))->isExpired());
    }

    public function test_arabic_labels(): void
    {
        app()->setLocale('ar');

        $c = Countdown::for(CarbonImmutable::parse('2026-10-15'), CarbonImmutable::parse('2026-10-19'), CarbonImmutable::parse('2026-10-19'));

        $this->assertSame('تنتهي اليوم', $c->label());
    }
}
