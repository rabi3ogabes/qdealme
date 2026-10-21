<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Describes where a flyer's validity window sits relative to "today" in the
 * shopper's city. All comparisons are made on calendar dates, so a flyer that
 * ends on the 20th is still valid for the whole of the 20th.
 */
final class Countdown
{
    private function __construct(
        public readonly string $state,
        public readonly int $days,
    ) {}

    public static function for(CarbonInterface $validFrom, CarbonInterface $validUntil, ?CarbonInterface $today = null): self
    {
        // Compare calendar dates only. "Today" is taken in the shopper's
        // timezone, while validity dates are plain dates, so each is reduced
        // to its Y-m-d in its own zone before comparing.
        $today = self::date($today ?? today(config('qdealme.timezone')));
        $from = self::date($validFrom);
        $until = self::date($validUntil);

        if ($from->greaterThan($today)) {
            return new self('upcoming', (int) $today->diffInDays($from));
        }

        if ($until->lessThan($today)) {
            return new self('expired', (int) $until->diffInDays($today));
        }

        return new self('active', (int) $today->diffInDays($until));
    }

    private static function date(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $value->format('Y-m-d'), 'UTC');
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }

    public function isUpcoming(): bool
    {
        return $this->state === 'upcoming';
    }

    public function isExpired(): bool
    {
        return $this->state === 'expired';
    }

    /**
     * Semantic tone used by the countdown chip.
     */
    public function tone(): string
    {
        return match (true) {
            $this->isExpired() => 'muted',
            $this->isUpcoming() => 'info',
            $this->days === 0 => 'danger',
            $this->days <= 2 => 'warning',
            default => 'neutral',
        };
    }

    public function label(): string
    {
        if ($this->isExpired()) {
            return __('countdown.expired');
        }

        if ($this->isUpcoming()) {
            return $this->days === 1
                ? __('countdown.starts_tomorrow')
                : __('countdown.starts_in', ['days' => fmt_number($this->days)]);
        }

        return match ($this->days) {
            0 => __('countdown.ends_today'),
            1 => __('countdown.ends_tomorrow'),
            default => __('countdown.days_left', ['days' => fmt_number($this->days)]),
        };
    }
}
