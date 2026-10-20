<?php

namespace App\Filament\Widgets;

use App\Enums\Verification;
use App\Filament\Widgets\Concerns\CountsPerDay;
use App\Models\Flyer;
use App\Models\Offer;
use Filament\Widgets\ChartWidget;

/**
 * Publishing activity: flyers published and offers verified per day.
 */
class FlyerActivityChart extends ChartWidget
{
    use CountsPerDay;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    public ?string $filter = '30';

    public function getHeading(): string
    {
        return __('admin.dashboard.activity');
    }

    public function getDescription(): string
    {
        return __('admin.dashboard.activity_hint');
    }

    protected function getFilters(): array
    {
        return [
            '7' => __('admin.dashboard.last_days', ['days' => 7]),
            '30' => __('admin.dashboard.last_days', ['days' => 30]),
            '90' => __('admin.dashboard.last_days', ['days' => 90]),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = in_array($this->filter, ['7', '30', '90'], true) ? (int) $this->filter : 30;

        $flyers = $this->countsPerDay(Flyer::query(), 'published_at', $days);
        $offers = $this->countsPerDay(Offer::query()->where('verification', Verification::Verified->value), 'verified_at', $days);

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.flyers_published'),
                    'data' => array_values($flyers),
                    'borderColor' => '#8e3358',
                    'backgroundColor' => 'rgba(142, 51, 88, 0.10)',
                    'pointBackgroundColor' => '#8e3358',
                    'fill' => true,
                    'tension' => 0.35,
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
                [
                    'label' => __('admin.dashboard.offers_verified'),
                    'data' => array_values($offers),
                    'borderColor' => '#c9a227',
                    'backgroundColor' => 'rgba(201, 162, 39, 0.08)',
                    'pointBackgroundColor' => '#c9a227',
                    'fill' => true,
                    'tension' => 0.35,
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => array_map(fn (string $day) => $this->dayLabel($day), array_keys($flyers)),
        ];
    }

    protected function getOptions(): array
    {
        $rtl = app()->getLocale() === 'ar';

        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'rtl' => $rtl,
                    'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle', 'boxWidth' => 8, 'padding' => 18],
                ],
                'tooltip' => ['rtl' => $rtl],
            ],
            'scales' => [
                'x' => ['reverse' => $rtl, 'grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8, 'autoSkip' => true]],
                'y' => ['position' => $rtl ? 'right' : 'left', 'beginAtZero' => true, 'ticks' => ['precision' => 0, 'maxTicksLimit' => 5], 'border' => ['display' => false]],
            ],
        ];
    }
}
