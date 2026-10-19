<?php

namespace App\Filament\Widgets;

use App\Enums\FlyerStatus;
use App\Enums\Verification;
use App\Models\ContactMessage;
use App\Models\Flyer;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\Report;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = today(config('qdealme.timezone'))->toDateString();

        return [
            Stat::make(__('admin.published_flyers'), Flyer::query()->published()->count())
                ->icon(Heroicon::OutlinedNewspaper)->color('success'),
            Stat::make(__('admin.flyer_in_review'), Flyer::query()->where('status', FlyerStatus::InReview->value)->count())
                ->icon(Heroicon::OutlinedEye)->color('warning'),
            Stat::make(__('admin.expiring_today'), Flyer::query()->published()->whereDate('valid_until', $today)->count())
                ->icon(Heroicon::OutlinedClock)->color('danger'),
            Stat::make(__('admin.failed_processing'), Flyer::query()->where('status', FlyerStatus::Failed->value)->count())
                ->icon(Heroicon::OutlinedExclamationTriangle)->color('danger'),
            Stat::make(__('admin.unverified_offers'), Offer::query()->where('verification', Verification::Unverified->value)->count())
                ->icon(Heroicon::OutlinedTag)->color('warning'),
            Stat::make(__('admin.open_reports'), Report::query()->where('status', 'open')->count())
                ->icon(Heroicon::OutlinedFlag),
            Stat::make(__('admin.new_leads'), Lead::query()->where('status', 'new')->count())
                ->icon(Heroicon::OutlinedBuildingOffice2),
            Stat::make(__('admin.models.contact_message.many'), ContactMessage::query()->where('status', 'new')->count())
                ->icon(Heroicon::OutlinedEnvelope),
        ];
    }
}
