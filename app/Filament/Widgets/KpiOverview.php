<?php

namespace App\Filament\Widgets;

use App\Enums\Verification;
use App\Filament\Widgets\Concerns\CountsPerDay;
use App\Models\Flyer;
use App\Models\Offer;
use App\Models\Retailer;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The four numbers that describe the platform at a glance, each with a
 * 14-day trend where one exists.
 */
class KpiOverview extends StatsOverviewWidget
{
    use CountsPerDay;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $published = $this->countsPerDay(Flyer::query(), 'published_at', 14);
        $verified = $this->countsPerDay(Offer::query()->where('verification', Verification::Verified->value), 'verified_at', 14);
        $publishedThisWeek = array_sum(array_slice($published, -7));
        $verifiedThisWeek = array_sum(array_slice($verified, -7));

        $activeStores = Retailer::query()->active()->count();
        $hiddenStores = Retailer::query()->where('is_active', false)->count();
        $awaiting = Offer::query()->where('verification', Verification::Unverified->value)->count();
        $views = (int) Flyer::query()->published()->sum('view_count');

        return [
            Stat::make(__('admin.kpi.live_flyers'), Number::format(Flyer::query()->published()->count()))
                ->icon(Heroicon::OutlinedNewspaper)
                ->description(__('admin.kpi.published_this_week', ['count' => Number::format($publishedThisWeek)]))
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->chart(array_values($published))
                ->color('primary'),
            Stat::make(__('admin.kpi.verified_offers'), Number::format(Offer::query()->where('verification', Verification::Verified->value)->count()))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->description($awaiting > 0
                    ? __('admin.kpi.awaiting_check', ['count' => Number::format($awaiting)])
                    : __('admin.kpi.verified_this_week', ['count' => Number::format($verifiedThisWeek)]))
                ->descriptionIcon($awaiting > 0 ? Heroicon::Clock : Heroicon::Check)
                ->descriptionColor($awaiting > 0 ? 'warning' : 'success')
                ->chart(array_values($verified))
                ->color('gold'),
            Stat::make(__('admin.kpi.active_stores'), Number::format($activeStores))
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->description(__('admin.kpi.hidden_stores', ['count' => Number::format($hiddenStores)]))
                ->descriptionIcon(Heroicon::EyeSlash)
                ->color('gray'),
            Stat::make(__('admin.kpi.flyer_views'), Number::abbreviate($views, maxPrecision: 1))
                ->icon(Heroicon::OutlinedChartBar)
                ->description(__('admin.kpi.across_live_flyers'))
                ->descriptionIcon(Heroicon::Eye)
                ->color('success'),
        ];
    }
}
