<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Daily counts for dashboard trends, bucketed by Qatar calendar day.
 */
trait CountsPerDay
{
    /**
     * @return array<string, int> Y-m-d => count, oldest first, including empty days
     */
    protected function countsPerDay(Builder $query, string $column, int $days): array
    {
        $tz = config('qdealme.timezone');
        $from = today($tz)->subDays($days - 1);

        $rows = (clone $query)
            ->whereNotNull($column)
            ->where($column, '>=', $from->copy()->utc())
            ->selectRaw("to_char(({$column} at time zone 'UTC') at time zone ?, 'YYYY-MM-DD') as day, count(*) as total", [$tz])
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($date = $from->copy(); $date->lte(today($tz)); $date = $date->addDay()) {
            $series[$date->toDateString()] = (int) ($rows[$date->toDateString()] ?? 0);
        }

        return $series;
    }

    protected function dayLabel(string $date): string
    {
        return Carbon::parse($date)->translatedFormat('j M');
    }
}
